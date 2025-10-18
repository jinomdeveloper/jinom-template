<?php

namespace Jinom\JinomTemplate\Services;

use Exception;

/**
 * Kelas untuk memanipulasi konten file PHP secara terprogram.
 */
class FileManipulator
{
    /**
     * Path lengkap ke file yang akan dimanipulasi.
     *
     * @var string
     */
    protected $filePath;

    /**
     * Konten file saat ini.
     *
     * @var string
     */
    protected $fileContents;

    /**
     * Constructor untuk kelas FileManipulator.
     *
     * @param  string  $filePath  Path ke file yang akan dimanipulasi.
     *
     * @throws Exception Jika file tidak ditemukan atau tidak dapat dibaca.
     */
    public function __construct(string $filePath)
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new Exception("File tidak ditemukan atau tidak dapat dibaca di path: {$filePath}");
        }
        $this->filePath = $filePath;
        $this->fileContents = file_get_contents($filePath);
    }

    public function addValidationRules(array $newRules): self
    {
        // Pola regex untuk menemukan array kosong atau yang sudah ada di dalam metode rules()
        $pattern = '/(public function rules\(\): array\s*\{\s*return\s*\[)([^\]]*)(\];)/s';

        return $this->replace($pattern, $newRules);
    }

    public function replace($pattern, array $data)
    {

        $dataString = $this->formatRulesToString($data);

        // Cek apakah pola ditemukan
        if (! preg_match($pattern, $this->fileContents)) {
            throw new Exception("Tidak dapat menemukan metode dengan format yang valid di file {$this->filePath}.");
        }

        // Menambahkan indentasi yang benar jika array sudah memiliki isi
        $replacementCallback = function ($matches) use ($dataString) {
            $before = $matches[1]; // Bagian sebelum isi array
            $existingContent = trim($matches[2]); // Isi array yang sudah ada
            $after = $matches[3];  // Bagian setelah isi array (']);')

            $newContent = $dataString;

            // Jika sudah ada konten, tambahkan koma dan baris baru sebelumnya
            if (! empty($existingContent)) {
                // Pastikan konten yang ada diakhiri dengan koma
                if (substr($existingContent, -1) !== ',') {
                    $existingContent .= ',';
                }
                $newContent = "\n".$existingContent."\n".$dataString;
            }

            return $before."\n \t\t\t".$newContent.$after;
        };

        // Lakukan penggantian menggunakan callback
        $newFileContents = preg_replace_callback($pattern, $replacementCallback, $this->fileContents);

        if ($newFileContents === null || $newFileContents === $this->fileContents) {
            throw new Exception('Gagal melakukan penambahan aturan validasi ke file.');
        }

        $this->fileContents = $newFileContents;

        return $this;
    }

    /**
     * Menyimpan perubahan kembali ke file asli.
     *
     * @return bool True jika berhasil, false jika gagal.
     */
    public function save(): bool
    {
        return file_put_contents($this->filePath, $this->fileContents) !== false;
    }

    /**
     * Mengubah array aturan menjadi string yang diformat untuk disisipkan ke file.
     */
    private function formatRulesToString(array $rules): string
    {
        $rulesString = '';
        $indentation = '            '; // 12 spasi untuk indentasi di dalam array
        foreach ($rules as $field => $rule) {
            $rulesString .= "\n".$indentation."'{$field}' => '{$rule}',";
        }

        // Menghapus koma terakhir jika ada dan menambahkan baris baru
        return rtrim(trim($rulesString), ',')."\n        ";
    }

    public function generateValidationRulesByColumnsScheme(array $schemaArray): array
    {
        $validationRules = [];
        $ignoredColumns = ['id', 'created_at', 'updated_at', 'deleted_at', 'email_verified_at', 'remember_token'];

        // 3. Looping untuk memproses setiap kolom
        foreach ($schemaArray as $column) {
            $fieldName = $column['name'];
            $rules = [];

            // Lewati kolom yang tidak perlu divalidasi
            if (in_array($fieldName, $ignoredColumns)) {
                continue;
            }

            // Aturan berdasarkan Nullable
            if ($column['nullable']) {
                $rules[] = 'nullable';
            } else {
                $rules[] = 'required';
            }

            // Aturan berdasarkan Tipe Data
            switch ($column['type_name']) {
                case 'varchar':
                case 'char':
                case 'text':
                case 'longtext':
                    $rules[] = 'string';

                    break;
                case 'timestamp':
                case 'date':
                case 'datetime':
                    $rules[] = 'date';

                    break;
                case 'int':
                case 'bigint':
                case 'smallint':
                case 'tinyint':
                    $rules[] = 'integer';

                    break;
                case 'decimal':
                case 'float':
                case 'double':
                    $rules[] = 'numeric';

                    break;
            }

            // Aturan berdasarkan Panjang Maksimal (untuk varchar, char)
            if (preg_match('/\((\d+)\)/', $column['type'], $matches)) {
                $maxLength = $matches[1];
                $rules[] = 'max:'.$maxLength;
            }

            // Aturan Khusus berdasarkan Nama Kolom (common conventions)
            if ($fieldName === 'email') {
                $rules[] = 'email';
                // Ganti 'users' dengan nama tabel Anda jika perlu
                $rules[] = 'unique:users,email';
            }

            if ($fieldName === 'password') {
                // Hapus aturan 'max' untuk password, ganti dengan aturan yang lebih umum
                $rules = array_filter($rules, fn ($rule) => strpos($rule, 'max:') !== 0);
                $rules[] = 'min:8';
                $rules[] = 'confirmed';
            }

            // Masukkan hasil ke array utama
            $validationRules[$fieldName] = implode('|', $rules);
        }

        return $validationRules;
    }
}
