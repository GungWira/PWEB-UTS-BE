<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoterImportController extends Controller
{
    private const COLUMNS = ['nik', 'nama', 'tanggal_lahir', 'rt_rw'];

    private const DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y'];

    public function template(): StreamedResponse
    {
        $fileName = 'template-dpt-pemira-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');

            // BOM UTF-8 agar Excel membaca huruf beraksen dengan benar.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, self::COLUMNS);
            fputcsv($handle, ['5103010101900001', 'I Wayan Sudiarta', '1990-01-01', '001/002']);
            fputcsv($handle, ['5103011503880002', 'Ni Luh Putu Anggreni', '1988-03-15', '002/002']);

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Baca dan verifikasi berkas tanpa menyimpan apa pun.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|max:5120']);

        $file = $request->file('file');

        if (! in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            return response()->json([
                'message' => 'Format berkas harus .csv (simpan dari Excel dengan "CSV UTF-8").',
            ], 422);
        }

        $rows = $this->readCsv($file->getRealPath());

        if ($rows === []) {
            return response()->json([
                'message' => 'Berkas kosong atau hanya berisi baris header.',
            ], 422);
        }

        return response()->json($this->verifyRows($rows));
    }

    /**
     * Simpan baris yang dikirim balik oleh frontend.
     *
     * Baris divalidasi ulang di sini supaya data dari browser tidak
     * langsung dipercaya.
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.nik' => 'required|string',
            'rows.*.name' => 'required|string',
            'rows.*.birth_date' => 'required|string',
            'rows.*.rt_rw' => 'nullable|string',
        ]);

        $existingNik = Voter::whereIn('nik', array_column($request->input('rows'), 'nik'))
            ->pluck('nik')
            ->map(fn ($nik) => (string) $nik)
            ->all();

        $payload = [];
        $seenNik = [];
        $skipped = 0;
        $timestamp = now();

        foreach ($request->input('rows') as $row) {
            $nik = trim((string) $row['nik']);
            $name = trim((string) $row['name']);
            $birthDate = $this->normalizeBirthDate((string) $row['birth_date']);
            $rtRw = isset($row['rt_rw']) ? trim((string) $row['rt_rw']) : '';

            $invalid = $this->findRowError($nik, $name, (string) $row['birth_date'], $rtRw) !== null;

            if ($invalid || $birthDate === null || isset($seenNik[$nik]) || in_array($nik, $existingNik, true)) {
                $skipped++;

                continue;
            }

            $seenNik[$nik] = true;

            $payload[] = [
                'nik' => $nik,
                'name' => $name,
                'birth_date' => $birthDate,
                'rt_rw' => $rtRw === '' ? null : $rtRw,
                'has_voted' => false,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($payload === []) {
            return response()->json([
                'message' => 'Tidak ada data baru yang bisa diimpor. Seluruh baris sudah terdaftar atau tidak valid.',
            ], 422);
        }

        foreach (array_chunk($payload, 500) as $chunk) {
            Voter::insert($chunk);
        }

        $message = count($payload).' data pemilih berhasil diimpor ke DPT.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' data dilewati karena sudah terdaftar atau tidak valid.';
        }

        return response()->json([
            'message' => $message,
            'imported' => count($payload),
            'skipped' => $skipped,
        ]);
    }

    /**
     * @return array<int, array{line: int, values: array<int, string>}>
     */
    private function readCsv(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        $handle = fopen('php://temp', 'r+b');
        fwrite($handle, (string) $contents);
        rewind($handle);

        $firstLine = (string) fgets($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $rows = [];
        $line = 0;

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;

            if ($line === 1) {
                continue;
            }

            $values = array_map(fn ($value): string => trim((string) $value), $values);

            if (implode('', $values) === '') {
                continue;
            }

            $rows[] = ['line' => $line, 'values' => $values];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Klasifikasikan setiap baris: valid, tidak valid, ganda dalam berkas,
     * atau sudah terdaftar di DPT.
     *
     * @param  array<int, array{line: int, values: array<int, string>}>  $rows
     * @return array<string, mixed>
     */
    private function verifyRows(array $rows): array
    {
        $existingNik = Voter::pluck('nik')->map(fn ($nik) => (string) $nik)->all();

        $validRows = [];
        $preview = [];
        $invalid = [];
        $duplicateInFile = [];
        $duplicateInDatabase = [];
        $seenNik = [];

        foreach ($rows as $row) {
            $line = $row['line'];
            $nik = $row['values'][0] ?? '';
            $name = $row['values'][1] ?? '';
            $rawBirthDate = $row['values'][2] ?? '';
            $rtRw = $row['values'][3] ?? '';

            if ($reason = $this->findRowError($nik, $name, $rawBirthDate, $rtRw)) {
                $invalid[] = ['line' => $line, 'nik' => $nik, 'name' => $name, 'reason' => $reason];

                continue;
            }

            if (isset($seenNik[$nik])) {
                $duplicateInFile[] = [
                    'line' => $line,
                    'nik' => $nik,
                    'name' => $name,
                    'reason' => 'NIK ganda dalam berkas (sudah ada di baris '.$seenNik[$nik].').',
                ];

                continue;
            }

            if (in_array($nik, $existingNik, true)) {
                $duplicateInDatabase[] = [
                    'line' => $line,
                    'nik' => $nik,
                    'name' => $name,
                    'reason' => 'NIK sudah terdaftar di DPT.',
                ];

                continue;
            }

            $seenNik[$nik] = $line;
            $birthDate = $this->normalizeBirthDate($rawBirthDate);

            $validRows[] = [
                'nik' => $nik,
                'name' => $name,
                'birth_date' => $birthDate,
                'rt_rw' => $rtRw === '' ? null : $rtRw,
            ];

            $preview[] = [
                'line' => $line,
                'nik' => $nik,
                'name' => $name,
                'birth_date' => $birthDate,
                'rt_rw' => $rtRw === '' ? '-' : $rtRw,
            ];
        }

        return [
            'total_read' => count($rows),
            'total_valid' => count($validRows),
            'valid_rows' => $validRows,
            'preview' => array_slice($preview, 0, 50),
            'invalid' => $invalid,
            'duplicate_in_file' => $duplicateInFile,
            'duplicate_in_database' => $duplicateInDatabase,
        ];
    }

    private function findRowError(string $nik, string $name, string $birthDate, string $rtRw): ?string
    {
        if ($nik === '') {
            return 'Kolom NIK wajib diisi.';
        }

        // Excel sering mengubah NIK 16 digit menjadi 5,1030101019E+15.
        if (preg_match('/e\+?\d+/i', $nik)) {
            return 'NIK terbaca sebagai notasi ilmiah ('.$nik.'). Format kolom NIK sebagai Teks di Excel, lalu simpan ulang.';
        }

        if (! preg_match('/^\d{16}$/', $nik)) {
            return 'NIK harus tepat 16 digit angka (terbaca '.mb_strlen($nik).' karakter).';
        }

        if ($name === '') {
            return 'Kolom nama wajib diisi.';
        }

        if (mb_strlen($name) > 255) {
            return 'Nama melebihi 255 karakter.';
        }

        if ($birthDate === '') {
            return 'Kolom tanggal lahir wajib diisi (dipakai pemilih untuk login).';
        }

        if ($this->normalizeBirthDate($birthDate) === null) {
            return 'Tanggal lahir "'.$birthDate.'" tidak valid. Gunakan format YYYY-MM-DD.';
        }

        if (mb_strlen($rtRw) > 50) {
            return 'RT/RW melebihi 50 karakter.';
        }

        return null;
    }

    private function normalizeBirthDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            $date = DateTime::createFromFormat('!'.$format, $value);

            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
