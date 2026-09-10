<?php

class rsBuilders {
    
    # other source builder
    
    private const _ORI_ = [
        'screenWidth' => '806',
        'screenHeight' => '320',
        'availWidth' => '806',
        'availHeight' => '320',
        'colorDepth' => '24',
        'pixelDepth' => '24',
        'innerHeight' => '320',
        'innerWidth' => '806',
        'platform' => 'Linux armv81',
        'appCodeName' => 'Mozilla',
        'hardwareConcurrency'=> '8',
    ];

    private const _GEN_ = [
        'screen_0' => 'screenWidth',
        'screen_1' => 'screenHeight',
        'screen_2' => 'availWidth',
        'screen_3' => 'availHeight',
        'screen_4' => 'colorDepth',
        'screen_5' => 'pixelDepth',
        'navigator_0' => 'appCodeName',
        'navigator_1' => 'appCodeName',
        'navigator_2' => 'mozFlag',
        'clientInfo_0'=> 'platform',
        'clientInfo_1'=> 'hardwareConcurrency',
        'window_0' => 'innerHeight',
        'window_1' => 'innerWidth',
        'document_0' => 'hasFocus',
        'click_0' => 'clickX',
        'click_1' => 'clickY',
        'timestamp' => 'timestamp',
    ];

    public function build($x, $y, $html) {
        $_scjs = $this->deobfuscate($html);
        $_ordr = $_scjs ? $this->extractFieldOrder($_scjs) : $this->defaultOrder();
        return $this->generateToken($x, $y, $_ordr);
    }

    private function generateToken($x, $y, array $order) {
        
        $dynamic = [
            'timestamp' => (string) time(),
            'clickX' => (string) $x,
            'clickY' => (string) $y,
        ];

        $static = array_merge(self::_ORI_, [ 'hasFocus' => '1', 'mozFlag'  => '0',]);

        $values = [];
        foreach ($order as $field) {
            $key = self::_GEN_[$field['source']] ?? '';
            $values[] = $dynamic[$key] ?? $static[$key] ?? '0';
        }

        return base64_encode(implode(',', $values));
        
    }

    private function deobfuscate($html) {
        
        if (!preg_match('/\}\("([^"]+)",\d+,"([^"]+)",(\d+),(\d+),\d+\)\)/', $html, $m)) return null;
        
        [$_enc, $_alp, $_shf, $_bse] = [$m[1], $m[2], (int)$m[3], (int)$m[4]];
        
        if ($_bse >= strlen($_alp)) return null;
        
        $_sep = $_alp[$_bse];
        $_res = '';
        
        foreach (explode($_sep, $_enc) as $seg) {
            
            if ($seg === '') continue;
            
            $_cvr = $seg;
            for ($j = 0; $j < strlen($_alp); $j++) {
                $_cvr = str_replace($_alp[$j], (string)$j, $_cvr);
            }
            $_chr = $this->baseConvert($_cvr, $_bse) - $_shf;
            if ($_chr > 0 && $_chr < 65536) $_res .= mb_chr($_chr);
        }

        return $_res ?: null;
    }

    private function baseConvert($_enc, $_bse) {
        $_res = 0;
        $chars  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ+/';
        $src = substr($chars, 0, $_bse);
        $len = strlen($_enc);

        for ($i = 0; $i < $len; $i++) {
            $pos = strpos($src, $_enc[$len - 1 - $i]);
            if ($pos !== false) $_res += $pos * (int)pow($_bse, $i);
        }

        return $_res;
    }

    private function extractFieldOrder($js): array {
        
        $_b64 = strpos($js, 'btoa');
        if ($_b64 === false) return $this->defaultOrder();

        $_sct = substr($js, $_b64, 3000);

        preg_match('/\((_0x[a-f0-9]+),/', $_sct, $first);
        preg_match_all('/\),(_0x[a-f0-9]+)\)/', $_sct, $rest);

        $order = array_merge($first[1] ? [$first[1]] : [], array_slice($rest[1], 0, 16));

        if (count($order) < 17) return $this->defaultOrder();

        $map = [];
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*screen\[/',             $map, 'screen', 6);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*navigator\[/',           $map, 'navigator', 3);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*clientInformation\[/', $map, 'clientInfo', 2);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*Math\[.*?\]\(window\[.*?\]\)/', $map, 'window', 2);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*document\[/',            $map, 'document', 1);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*Math\[.*?\]\(_0x.*?\[/',$map, 'click', 2);

        if (preg_match('/(_0x[a-f0-9]+)\s*=\s*~~_0x/', $js, $m)) $map[$m[1]] = 'timestamp';

        return array_map(fn($v) => ['source' => $map[$v] ?? 'unknown', 'is_flag' => false], array_slice($order, 0, 17));
    }

    private function mapVars($js, $pattern, array &$map, $prefix, $limit) {
        preg_match_all($pattern, $js, $m);
        foreach (array_slice($m[1], 0, $limit) as $i => $v) $map[$v] = "{$prefix}_{$i}";
    }

    private function defaultOrder() {
        return [
            ['source' => 'screen_4', 'is_flag' => false],
            ['source' => 'navigator_0', 'is_flag' => true ],
            ['source' => 'click_1', 'is_flag' => false],
            ['source' => 'click_0', 'is_flag' => false],
            ['source' => 'document_0', 'is_flag' => true],
            ['source' => 'screen_1', 'is_flag' => false],
            ['source' => 'navigator_1', 'is_flag' => false],
            ['source' => 'navigator_2', 'is_flag' => true],
            ['source' => 'window_0', 'is_flag' => false],
            ['source' => 'clientInfo_0','is_flag' => false],
            ['source' => 'screen_0', 'is_flag' => false],
            ['source' => 'window_1', 'is_flag' => false],
            ['source' => 'screen_2', 'is_flag' => false],
            ['source' => 'timestamp', 'is_flag' => false],
            ['source' => 'document_0', 'is_flag' => true],
            ['source' => 'navigator_2', 'is_flag' => true],
            ['source' => 'screen_5', 'is_flag' => false],
        ];
    }
}
