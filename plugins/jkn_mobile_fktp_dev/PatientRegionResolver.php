<?php
namespace Plugins\JKN_Mobile_FKTP_Dev;

/** Resolves the region sent by BPJS to an exact local master row; never falls back silently. */
final class PatientRegionResolver
{
    public static function resolve(\PDO $pdo, array $input)
    {
        $definitions = [
            'kd_prop' => ['propinsi', 'kd_prop', 'nm_prop', 'kodeprop', 'namaprop', true, 'provinsi'],
            'kd_kab'  => ['kabupaten', 'kd_kab', 'nm_kab', 'kodedati2', 'namadati2', true, 'kabupaten/kota'],
            'kd_kec'  => ['kecamatan', 'kd_kec', 'nm_kec', 'kodekec', 'namakec', true, 'kecamatan'],
            'kd_kel'  => ['kelurahan', 'kd_kel', 'nm_kel', 'kodekel', 'namakel', false, 'kelurahan/desa'],
        ];
        $resolved = [];
        foreach ($definitions as $target => $definition) {
            [$table, $idColumn, $nameColumn, $codeField, $nameField, $integer, $label] = $definition;
            $code = trim((string) ($input[$codeField] ?? ''));
            $name = trim((string) ($input[$nameField] ?? ''));
            if ($code === '' || $name === '') {
                throw new \DomainException('Kode dan nama '.$label.' dari BPJS wajib diisi.');
            }

            // Prefer the BPJS code only when both code and name point to the same local master row.
            $byCode = $pdo->prepare(
                'SELECT '.$idColumn.' FROM '.$table.' WHERE '.$idColumn.' = ? ' .
                'AND LOWER(TRIM('.$nameColumn.')) = LOWER(TRIM(?)) LIMIT 1'
            );
            $byCode->execute([$code, $name]);
            $value = $byCode->fetchColumn();

            if ($value === false) {
                // mLITE commonly uses internal numeric identifiers. In that case map the BPJS
                // value through the exact name, but reject ambiguous or absent master data.
                $byName = $pdo->prepare(
                    'SELECT '.$idColumn.' FROM '.$table.' ' .
                    'WHERE LOWER(TRIM('.$nameColumn.')) = LOWER(TRIM(?)) ORDER BY '.$idColumn.' ASC LIMIT 2'
                );
                $byName->execute([$name]);
                $matches = $byName->fetchAll(\PDO::FETCH_COLUMN);
                if (count($matches) !== 1) {
                    $reason = count($matches) > 1 ? 'ambigu' : 'belum tersedia';
                    throw new \DomainException(
                        'Wilayah '.$label.' BPJS "'.$name.'" (kode '.$code.') '.$reason.' di master mLITE. ' .
                        'Lengkapi master wilayah sebelum mendaftarkan pasien.'
                    );
                }
                $value = $matches[0];
            }
            $resolved[$target] = $integer ? (int) $value : (string) $value;
        }
        return $resolved;
    }
}
