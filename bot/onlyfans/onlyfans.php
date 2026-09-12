<?php

return (new class {
    
    use Base, Mimic;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://onlyfaucet.com';
    private string $r = '/?r=88049';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = true;
    private bool $SLDONE = false;
    private bool $ADDONE = false;
    private array $headersCF = [];
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        $this->acc = Config::credential(['ua' => fn() => Config::uagent('nolinux')], false, ['login', 'PROXY']);
        putenv("PROXY=" . $this->acc['PROXY']);
        
        Proxy::load();
        Check::Geo();
        
        $this->mail = $this->acc['login'];
        
        Inf::setup(
            $this->acc['ua'],
            Config::cookie($this->mail),
            $this->ip,
            false, 
            $this->mail
        );
        
        $b = $this->banner = Banner::getInstance();
        $b->show();
        $b->task1('ok', $this->mail);
        $b->task2('ok', "site: " . $this->host);
    }
    
    public function exec() {
        $claimed = 0;
        $habis = [];
        $curr = '';
        $skipped = [];
        
        $this->headersCF = inf::Nethead(array_merge($this->headersCF, $this->adcookie()));
        
        login:
            Proxy::load();
            Check::Geo();
        
        $this->_ck();
        $this->generateFingerprint($this->acc['ua']);
        
        while (true) {
            $dash = null;
            $ret = 0;
            
            do {
                $ret++;
                $l = Inf::check("{$this->host}", $this->headersCF, '/auth/login');
                
                if ($l['ok']) {
                    $dash = $l['html'];
                    logx('Info', "logged in", false); 
                    _sle(3); _clr();
                    #var_dump($dash); die;
                    break;
                }
                
                if ($ret >= 10) $this->logger('err', "can't login", 'RETRY LIMIT REACHED, CHECK BROWSER', true);
                
                Logger::X('err', "logging in", false); 
                _sle(3); _clr();
                $po = null;
                
                $_0 = Net::X($this->host.$this->r, 'GET', null, Inf::$cookie, $this->headersCF, '', Inf::$uagent, d: true);
                $_0 = $this->checkCF($this->headersCF, $this->host, $_0);
                
                if (!empty($_0) && $_0 !== 99) {
                    $f = Scraper::payload($_0)[0] ?? null;
                    #var_dump($f); die;
                    
                    if (!empty($f)) {
                        $pa = $f['payload'];
                        $cre = ['wallet' => $this->mail];
                        #$cap = $this->_cp($_0);
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                        
                    }
                }
                
                if ($po) {
                    #print_r($po); die;
                    $ve = Net::X($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $this->host.$this->r, Inf::$uagent);
                    #_put('ve.html', $ve); #die; _rl('cek ve: ');
                    
                    if ((stripos($ve, 'Just a moment') !== false)) {
                        $this->tesONF($f['url'], $f['payload']);
                    }
                    
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash); die;
            
            $_fa = Scraper::_xP($dash, "//ul[@id='faucet']//a/@href");
            #print_r($_fa);
            if (empty($curr)) shuffle($_fa);
            if ($this->claim) {
                foreach ($_fa as $fa) {
                    
                    $_c = basename(parse_url($fa)['path']);
                    if (!empty($curr) && !str_contains($_c, $curr)) continue;
                    
                    if (isset($habis[$fa])) {
                        $curr = '';
                        continue;
                    }
                    
                    print(FGd['CYN']." ".ITAL.'processing  ');
                    Logger::X('err', $_c);
                    
                    $ret99 = 0;
                    while (true) {
                        if ($claimed >= 2) {
                            styler("waiting for next minute", fn() => _sle(7));
                            $claimed = 0;
                        }
                        $ret99++;
                        
                        $fau = Net::X($fa, 'GET', null, Inf::$cookie, $this->headersCF, $fa, Inf::$uagent, d: true);
                        
                        if ($fau === 99) {
                            if ($ret99 >= 5) goto login;
                            continue;
                        }
                        $ret99 = 0;
                        
                        $fau = $this->checkCF($this->headersCF, $fa, $fau);
                        
                        #_put('fau.html', $fau);
                        if ($ban = $this->isBan($fau)) {
                            if (!$this->SLDONE) {
                                $curr = $_c;
                                break;
                            }
                            styler("waiting for unlocked {$ban['tmr']}", fn() => _sle($ban['sleep']));
                            continue;
                        }
                        
                        $po = null;
                        if (!empty($fau) && $fau !== 99) {
                            $f = Scraper::payload($fau, 'fauform')[0] ?? null;
                            #var_dump($f); die;
                            
                            if (!empty($f)) {
                                
                                $pa = $f['payload'];
                                
                                #$cap = $this->_cp($fau);
                                $cap = Solve::exec($fau, $this->host, $this->api, $pa);
                                
                                if (isset($cap['nocaptcha']) && isset($pa['captcha_answer'])) $cap = $this->onfCap($fau, $this->host, $fa, $this->api);
                                #var_dump($cap);
                                if (isset($cap['trouble'])) {
                                    _sle(10);
                                    continue;
                                }
                                $po = array_merge($pa, $cap);
                                
                            } else {
                                if (str_contains($fau, '/auth/login')) continue 3;
                            }
                            
                        }
                        
                        if (!empty($po)) {
                            #print_r($po); #die;
                            
                            $cla = Net::X($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $fa, Inf::$uagent);
                            #_put('cla.html', $cla); #die;
                            
                            $mf = Scraper::_jP($cla, "/Toast\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
                            if (!empty($mf[2][0])) {
                                $claimed++;
                                
                                $stt = $mf[1][0];
                                $msg = $mf[2][0];
                                $this->logger($stt, 'fct', $msg);
                                
                                if (preg_match('/sufficient|could not be processed/i', $msg)) {
                                    $habis[$fa] = true;
                                    break;
                                }
                                
                                if (preg_match('/blacklisted|flagged|banned/i', $msg)) die;
                                
                                if (preg_match('/cation failed/i', $msg)) continue 3;
                                
                                if (stripos($msg, 'Shortlink') || preg_match('/went wron/i', $msg)) {
                                    if ($this->SLDONE) die;
                                    $curr = $_c;
                                    break 2;
                                }
                                
                            } else {
                                if ((stripos($cla, 'Just a moment') !== false)) {
                                    unset($po['nocaptcha']);
                                    $this->tesONF($f['url'], $po);
                                    continue;
                                }
                            }
                            
                            #die;
                            styler("waiting for next claim", fn() => _sle(rand(10, 13)));
                        }
                        
                    }
                    
                }
            }
            
            if (count($habis) === count($_fa)) $this->logger('ok', '', 'beres', 1);
            
            $_sl = Scraper::_xP($dash, "//ul[@id='links']//a/@href");
            #print_r($_sl);
            foreach ($_sl as $sl) {
                $_c = basename($sl);
                if (!empty($curr) && !str_contains($_c, $curr)) continue;
                
                $up = ['earnow','shortano', 'shortino', 'fc-lc', 'coinclix'];
                $ret99 = 0;
                do {
                    $ret99++;
                    $sho = null;
                    $sho = Net::X($sl, 'GET', null, Inf::$cookie, $this->headersCF, '', Inf::$uagent);
                    #_put('sho.html', $sho);
                    if ($sho === 99) {
                        if ($ret99 >= 5) goto login;
                        continue;
                    }
                    $ret99 = 0;
                    
                    $short = Shortlinks::extract($sho);
                    if (empty($short)) continue 3;
                    #print_r($short); die;
                    
                    $success_in_page = false;
                    $found_one = false;
                    
                    foreach ($short as $links => [$idd, $lmt]) {
                        if (!Shortlinks::limit($lmt) || isset($skipped[$idd])) continue;
                        
                        $found_one = true;
                        $loc = $this->parseShortL($idd, $sl);
                        
                        if (!$loc) {
                            $skipped[$idd] = true; 
                            continue;
                        }
                        #var_dump($loc);
                        $loc_u = parse_url($loc['url'])['host'] ?? '';
                        $is_bl = false;
                        foreach ($up as $blacklisted) {
                            if (str_contains($loc_u, $blacklisted)) {
                                logx('warn', "Domain $blacklisted Skipping..");
                                $skipped[$idd] = true;
                                $is_bl = true;
                                break; 
                            }
                        }
                        if ($is_bl) continue;
                        
                        $start = microtime(true);
                        $bakk = Shortlinks::exec($this->api, $loc['url']);
                        $wait = 130 - (int)(microtime(true) - $start);
                        
                        if (!$bakk) {
                            $skipped[$idd] = true; 
                            continue;
                        }
                        
                        if ($wait > 0) styler("waiting {$wait}.s for SL", fn() => _sle((int)ceil($wait)));
                        
                        $retVer = 0;
                        while ($retVer <= 3) {
                            $retVer++;
                            $ver = Net::X($bakk, 'GET', null, Inf::$cookie, $this->headersCF, $loc['url'], Inf::$uagent);
                            #_put('ver.html', $ver);
                            
                            if (!empty($ver) && $ver !== 99) {
                                $po = null;
                                $f = Scraper::payload($ver, 'claimForm')[0] ?? null;
                                if (!empty($f)) {
                                    $pa = $f['payload'];
                                    
                                    $cap = Solve::exec($ver, $this->host, $this->api);
                                    $po = array_merge($pa, $cap);
                                    
                                }
                                
                                if (!empty($po)) {
                                    $cla = Net::X($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent);
                                    
                                    $msh = Scraper::_jP($cla, "/Toast\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
                                    #var_dump($msh);
                                    
                                    if (!empty($msh[2][0])) {
                                        $stt = $msh[1][0];
                                        $msg = $msh[2][0];
                                        $this->logger($stt, 'sho', $msg);
                                        
                                        if (preg_match('/sufficient|could not be processed/i', $msg)) {
                                            $sidx = array_search($sl, $_sl);
                                            
                                            if ($sidx !== false && isset($_sl[$sidx + 1])) $curr = basename($_sl[$sidx + 1]);
                                            
                                            else $curr = '';
                                            
                                        }
                                        break 3;
                                    }
                                    
                                    if (stripos($cla, 'has been sent')) $success_in_page = true;
                                
                                    
                                }
                                
                                $success_in_page = true;
                                
                                break 3;
                            }
                        }
                    }
                    if (!$found_one) {
                        $this->logger('err', 'sho', 'SL habis atau sisa blacklist');
                        $this->SLDONE = true;
                        break; 
                    }
                    
                } while (!$success_in_page);
                
                if ($success_in_page || $curr === "") break; 
                
            }
            
        }
        
        
        
        
    }
    
    private function onfCap($html, $host, $reff) {
        
        $img = null;
        $x_cap = ['ins' => 'ASC', 'cnt' => 3];
        $warna = null;
        $wtype = null;
        $req = null;
        $mungkin_tanpa_fp = false;
        
        $init = json_decode(Net::X(
            $host.'/faucet/init_captcha?_t=' . (time() * 1000), 
            'GET', null, Inf::$cookie, $this->headersCF, 
            $reff, Inf::$uagent
        )?: '', 1)['token'] ?? null;
        
        if ($init) {
            $req = Net::X(
                $host.'/faucet/captcha_image?token=' . $init, 
                'GET', null, Inf::$cookie, $this->headersCF, 
                $reff, Inf::$uagent, d: true
            );
        }
        
        #_put('img.png', $req['body']); die;
        #unset($req['body']);
        #var_dump($req['headers']); #die;
        
        
        if (!empty($req) && $req !== 99) {
            
            
            $img = $req['body'] ?? null;
            $x_pow = [
                'salt' => $req['headers']['x-pow-salt'][0] ?? '',
                'diff' => (int)($req['headers']['x-pow-difficulty'][0] ?? 2)
            ];
            
            $sequence = $req['headers']['x-captcha-prompt-sequence'][0] ?? null;
            #var_dump($req['headers'], $sequence);
            
            if (str_contains($html, 'destinations in order') && !empty($sequence)) {
                
                $warna = [
                    'ins' => $sequence,
                    'cnt' => count(explode(',', $sequence))
                ];
                $wtype = 'necaptcha';
            } elseif (str_contains($html, 'Follow the dashed wire to the other side')) {
                $warna = [
                    'ins' => $req['headers']['x-captcha-color-name'][0] ?? '',
                    'cnt' => (int)($req['headers']['x-captcha-target-count'][0] ?? 1)
                ];
                $wtype = 'necaptcha';
            } elseif (preg_match('/Match the animation|Match the movement type/i', $html)) {
                
                $mungkin_tanpa_fp = true;
                
                $warna = [
                    'ins' => '',
                    'cnt' => 1
                ];
                $wtype = 'necaptcha';
                
                
            }
            
            $x_cap = $warna ?? [
                'ins' => $req['headers']['x-captcha-instruction'][0] ?? 'ASC',
                'cnt' => (int)($req['headers']['x-captcha-target-count'][0] ?? 3)
            ];
            $setCAP = microtime(true);
            
            $trap = ($req['headers']["x-captcha-trap-key"][0] ?? '') && ($req['headers']["x-captcha-trap-val"][0] ?? '') ?? null;
        }
        
        if (!empty($img)) {
            
            $trapData = [];
            if ($trap) {
                $trapData = [
                    $req['headers']["x-captcha-trap-key"][0] => $req['headers']["x-captcha-trap-val"][0],
                    'captcha_instruction' => $x_cap['ins'],
                    'captcha_target_count' => $x_cap['cnt']
                ];
            }
            
            #_put('img.png', $img); #die;
            #var_dump($x_cap); #die;
            
            $captype = $wtype ?? 'onlyfans';
            $cappart = $warna ?? $x_cap;
            
            $solution = Solve::img($this->api, $reff, $captype, $img, $cappart);
            if (isset($solution['trouble'])) return ['trouble' => 'reload'];
            
            if ($mungkin_tanpa_fp) {
                #var_dump($solution);
                _sle(3);
                return ['captcha_answer' => (string)$solution];
            }
            
            preg_match_all('/x[=:\s]*(\d+)[,\s]*y[=:\s]*(\d+)/i', $solution, $matches, PREG_SET_ORDER);
            if (count($matches) < $x_cap['cnt']) return ['trouble' => 'reload'];
            
            if ($x_cap['ins'] === 'DESC' && !$warna) $matches = array_reverse($matches);
            
            $clk = array_slice($matches, 0, $x_cap['cnt']);
            $mdt = [];
            $ANS = [];
            $setCLK = microtime(true);
            
            foreach ($clk as $index => $match) {
                $delay = $index === 0 ? mt_rand(800000, 1200000) : mt_rand(400000, 700000);
                usleep($delay);
                
                $current = (microtime(true) - $setCLK) * 1000;
                
                $x = (int)max(0, min(449, $match[1]));
                $y = (int)max(0, min(279, $match[2]));
                
                $ANS[] = "$x,$y";
                $mdt[] = [
                    'x' => $x,
                    'y' => $y,
                    't' => (int)$current
                ];
            }
            
            $waktu = (int)((microtime(true) - $setCAP) * 1000);
            $bfp = $this->onfFPS(Inf::$uagent, $mdt, $waktu, $trapData);
            $powRes = SolveUtils::Pow($x_pow['salt'], $x_pow['diff']);
            
            return [
                'pow_nonce' => $powRes['nonce'] ?? 0,
                'captcha_answer' => implode(';', $ANS),
                'browser_fingerprint' => $bfp
            ];
        }
        
        return ['trouble' => 'reload'];
    }
    
    public function onfFPS($ua, array $mouse, int $waktu, $trap = []) {
        
        static $st = null;
        
        if (empty($st)) {
            $sth = md5("{$this->mail}|{$ua}");
            $st = 'st_'.substr($sth, 0, 8).substr($sth, 8, 8); 
        }
    
        $hwDetails = array_merge([
            'st' => $st,
            'gl' => $this->webglFingerprint['renderer'] ?? 'ANGLE (NVIDIA, NVIDIA GeForce RTX 3060, OpenGL 4.5)',
            'tz' => TIMEZONE(),
            'sw' => $this->screenFingerprint['availWidth'] ?? 1920,
            'sh' => $this->screenFingerprint['availHeight'] ?? 1040,
            'wd' => false,
            'chr' => true,
            'ua' => $ua
        ], ($trap ?? []));
    
        return base64_encode(json_encode([
            'solve_time_ms' => $waktu,
            'hardware_hash' => $this->djb2(json_encode($hwDetails, JSON_UNESCAPED_SLASHES)),
            'webdriver' => $hwDetails['wd'] ? 1 : 0,
            'mouse_data' => array_values($mouse),
            'raw' => array_merge(
                [
                    'iw' => $this->screenFingerprint['innerWidth'] ?? 1920,
                    'ih' => $this->screenFingerprint['innerHeight'] ?? 1080
                ],
                $hwDetails
            )
        ], JSON_UNESCAPED_SLASHES));
    }
    
    private function djb2($str) {
        $hash = 5381;
        for ($i = (strlen($str) - 1); $i >= 0; $i--) $hash = ((($hash * 33) & 0xFFFFFFFF) ^ ord($str[$i])) & 0xFFFFFFFF;
        $sign = sprintf('%u', $hash & 0xFFFFFFFF);
        return base_convert($sign, 10, 16);
    }
    
    private function parseShortL($ud, $sl) {
        $curr = basename($sl);
        $token = json_decode(Net::X("{$this->host}/links/get_csrf_token", 'GET', [], Inf::$cookie, [], $sl, Inf::$uagent, true)?: '', 1)['csrf_hash'] ?? null;
        
        if ($token) {
            $payload = [
                'link_id' => $ud,
                'cur' => strtoupper($curr),
                'csrf_token_name' => $token
            ];
            
            $short = json_decode(
                    Net::X("https://onlyfaucet.com/links/verify_go",
                           'POST',
                           SolveUtils::webkitID($payload, $bon),
                           Inf::$cookie, 
                           ["Content-Type: multipart/form-data; boundary=$bon"],
                           $sl,
                           Inf::$uagent)
                    ?: '', 1)['url'] ?? null;
            
            if ($short) return ['url' => $short, 'tkn' => $token];
            
        }
        
        return null;
            
    }
    
    private function _ck() {
        $ccc = $this->adcookie(true);
        foreach ($ccc as $nn => $vv) Inf::injectCookie(Inf::$cookie, $vv, $this->host, $nn);
    }
    
    private function tesONF($url, $po = null) {
        
        $payload = null;
        $cekk = Net::X($url, 'GET', $payload, Inf::$cookie, $this->headersCF, $url, Inf::$uagent, d: true);
        if (($cekk['http_code'] ?? 0) === 200 || !stripos(($cekk['body'] ?? ''), 'Just a moment')) {
            $payload = $po;
            $cekk = Net::X($url, 'POST', $payload, Inf::$cookie, $this->headersCF, $url, Inf::$uagent, d: true);
            if (!($cekk['http_code'] ?? null) === 200 || stripos($html, 'Just a moment')) {
                $this->logger('err', 'CLOUDFLARE', 'interstitial-post detected!!!');
            }
        }
        
        return $this->checkCF($this->headersCF, $url, $cekk, 0, $payload);
        
    }
    
})->exec();


