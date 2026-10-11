<?php

namespace BigFish\PDF417;

/**
 * Encodes data into PDF417 code words.
 *
 * Prilagođeno za Lovačku evidenciju (HUB-3): uvijek bajtni način (Byte compaction),
 * cjelobrojna aritmetika (bez bcmath), uvijek 5 kodnih riječi po 6 bajtova.
 */
class DataEncoder
{
    public function encode($data)
    {
        $data = (string) $data;
        $len = strlen($data);
        $codes = [$len % 6 === 0 ? 924 : 901];
        $pun = intdiv($len, 6) * 6;
        for ($i = 0; $i < $pun; $i += 6) {
            $n = 0;
            for ($j = 0; $j < 6; $j++) {
                $n = $n * 256 + ord($data[$i + $j]);
            }
            $cw = [];
            for ($j = 0; $j < 5; $j++) {
                array_unshift($cw, $n % 900);
                $n = intdiv($n, 900);
            }
            array_push($codes, ...$cw);
        }
        for ($i = $pun; $i < $len; $i++) {
            $codes[] = ord($data[$i]);
        }
        return $codes;
    }
}
