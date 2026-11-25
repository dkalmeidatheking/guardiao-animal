<?php
/**
 * Bairros usados no mapa do Guardião Animal.
 * Jardim Itamaraty e Jardim Santos Dumont: pontos de referência em ruas verificadas dos bairros.
 * Outros bairros: centros aproximados herdados do protótipo, exigem validação
 * com limites oficiais antes de alegar precisão geográfica de toda a cidade.
 */
function ourinhos_neighborhoods(): array {
    return [
        'Centro' => ['lat'=>-22.9795,'lng'=>-49.8705],
        'Jardim Itamaraty' => ['lat'=>-23.0146,'lng'=>-49.8808],
        'Jardim Matilde' => ['lat'=>-22.9960,'lng'=>-49.8815],
        'Jardim Paulista' => ['lat'=>-22.9665,'lng'=>-49.8870],
        'Jardim Santa Fé' => ['lat'=>-22.9550,'lng'=>-49.8830],
        'Vila Nova' => ['lat'=>-22.9700,'lng'=>-49.8545],
        'Vila Perino' => ['lat'=>-22.9870,'lng'=>-49.8575],
        'Jardim Ouro Verde' => ['lat'=>-22.9585,'lng'=>-49.8980],
        'Vila Musa' => ['lat'=>-23.0010,'lng'=>-49.8785],
        'Vila Soares' => ['lat'=>-22.9680,'lng'=>-49.9020],
        'Jardim Anchieta' => ['lat'=>-22.9590,'lng'=>-49.8490],
        'Parque Minas Gerais' => ['lat'=>-22.9535,'lng'=>-49.8670],
        'Jardim das Paineiras' => ['lat'=>-22.9650,'lng'=>-49.8740],
        'Vila Boa Esperança' => ['lat'=>-22.9890,'lng'=>-49.8490],
        'Jardim Santos Dumont' => ['lat'=>-22.9710,'lng'=>-49.9000],
        'Nova Ourinhos' => ['lat'=>-22.9690,'lng'=>-49.9160],
    ];
}
function ourinhos_lower(string $value): string {
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}
function normalize_ourinhos_bairro(string $bairro): ?string {
    $clean = trim($bairro);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean);
    $normalized = preg_replace('/\s+/', ' ', strtolower((string)($ascii ?: $clean)));
    $normalized = preg_replace('/[^a-z0-9 ]/', '', (string)$normalized);
    $aliases = [
       'itamaraty' => 'Jardim Itamaraty',
       'jd itamaraty' => 'Jardim Itamaraty',
       'santos dumont' => 'Jardim Santos Dumont',
       'jd santos dumont' => 'Jardim Santos Dumont',
       'jardim s dumont' => 'Jardim Santos Dumont',
    ];
    if (isset($aliases[$normalized])) return $aliases[$normalized];
    foreach (array_keys(ourinhos_neighborhoods()) as $name) {
        $raw = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $key = preg_replace('/[^a-z0-9 ]/', '', strtolower((string)($raw ?: $name)));
        if ($normalized === $key) return $name;
    }
    return null;
}
