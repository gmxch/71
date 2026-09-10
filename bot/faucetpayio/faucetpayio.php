<?php

$thumbmrk_key = "b0fb11eb71e2c393f1162331af6d3efa";
$api = onKeys();

if (!($api instanceof skibidixxx)) die(logx('err', 'pilih api skibidixxx'));

logx('err', "\nneed detailed info to prevent suspicious session, and email otp if possible");
logx('err', "gmxch api is also can get this digital key, (not recommended for prevent soft ban (ip binding))");

$acc = Config::credential([], false, ['PROXY']);
$host = "https://faucetpay.io";
$app = "https://api.faucetpay.io";

$b = Banner::getInstance();
$b->show();
$b->task1('ok', "use with caution");
$b->task2('ok', "");

(function () use ($acc) {
    $cookieFile = Config::cookie();
    $userAgent = $acc['user_agent'] ?? 'Mozilla/5.0';
    
    $proxy = $acc['PROXY'] ?? '';
    $thmb = $acc['thumbmark'];
    $vist = $acc['visitor_id'];
    
    if ($proxy) putenv("PROXY=$proxy");
    Inf::setup($userAgent, $cookieFile);
    Proxy::load();
    Check::Geo();
})();

$mailPath = __DIR__ . '/email.txt';
$mailJson = __DIR__ . '/email.json';
$autoOtp = false;

$jsonList = is_file($mailJson) ? json_decode(_get($mailJson), true) : null;

if (!is_array($jsonList) || empty($jsonList)) {
    if (!is_file($mailPath)) {
        die(logx('err', 'email.txt not found. Create & fill it first.'));
    }
    $mailList = file($mailPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    Logger::X('warn', "total mail: " . count($mailList));
    Logger::X('info', "is the entire account use same password?");
    while (true) {
        $conf = strtolower(trim(_rl('[ y/n ]: ')));
        if ($conf === 'y') {
            do { $pass = trim(_rl('password: ')); } while ($pass === '');
            foreach ($mailList as $mail) {
                $jsonList[] = ['mail' => $mail, 'pass' => $pass];
            }
            break;
        }
        if ($conf === 'n') {
            foreach ($mailList as $mail) {
                do { $pass = trim(_rl("pass for $mail: ")); } while ($pass === '');
                $jsonList[] = ['mail' => $mail, 'pass' => $pass];
            }
            break;
        }
        Logger::X('err', 'pilih y atau n');
    }
    _put($mailJson, json_encode($jsonList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

main_menu:
while (true) {
    $b->show();
    $b->task1('ok', "JSON Primary (" . count($jsonList) . " accounts loaded)");
    $b->task2('info', "Auto OTP: " . ($autoOtp ? 'ON' : 'OFF'));
    
    Logger::X('info', "[1] Reload / Update from email.txt", true, true);
    Logger::X('info', "[2] Auto OTP (Current: " . ($autoOtp ? 'ON' : 'OFF') . ")", true, true);
    Logger::X('info', "[3] Auto-Login - Claim RP", true, true);
    Logger::X('info', "[4] Balance - Send Menu", true, true);
    Logger::X('info', "[5] Reset All Auth", true, true);
    Logger::X('info', "[6] Exit", true, true);
    
    $choice = trim(_rl(' input: '));
    #$choice = "3";
    
    if ($choice === '1') {
        if (!is_file($mailPath)) {
            Logger::X('err', 'email.txt not found');
            _rl('Enter to continue...');
            continue;
        }
        $mailList = file($mailPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $existingMails = array_column($jsonList, 'mail');
        $updated = false;
        
        foreach ($mailList as $mail) {
            if (!in_array($mail, $existingMails)) {
                do { $pass = trim(_rl("New pass for $mail: ")); } while ($pass === '');
                $jsonList[] = ['mail' => $mail, 'pass' => $pass];
                $updated = true;
            }
        }
        
        if ($updated) {
            _put($mailJson, json_encode($jsonList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            Logger::X('ok', 'JSON updated. You can now run [3] to login new accounts.');
        } else {
            Logger::X('info', 'No new accounts found in email.txt');
        }
        _rl('Enter to continue...');
    } 
    elseif ($choice === '2') {
        $autoOtp = !$autoOtp;
        Logger::X('info', "Auto OTP set to: " . ($autoOtp ? 'ON' : 'OFF'));
        _rl('Enter to continue...');
    } 
    elseif ($choice === '3') {
        $b->task1('info', "Starting Auto-Login & Claim...");
        $loginCount = 0;

        foreach ($jsonList as $key => &$account) {
            if (empty($account['auth'])) {
                $b->task2('info', "Getting auth for: {$account['mail']}");
                
                $sol = _getBer($account, $acc, $api, $host, $autoOtp, $app);
                
                if ($sol === false) {
                    $b->task2('warn', "Skipped {$account['mail']} (Need 2FA & Auto OTP OFF)");
                    continue;
                }
                
                if ($sol) {
                    [$auth, $etag] = $sol;
                    $account['auth'] = $auth;
                    $account['etag'] = $etag;
                    _put($mailJson, json_encode($jsonList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    $b->task2('ok', "Saved Auth for {$account['mail']}");
                    $loginCount++;
                }
            }
        }
        unset($account);
        $b->task1('ok', $loginCount > 0 ? "Auto-Login finished. {$loginCount} processed." : "All accounts have Auth or skipped.");
        _rl('Enter to continue...');

        $b->show();
        /*
        $b->task1('info', "Claiming Daily RP...");
        foreach ($jsonList as $accData) {
            if (empty($accData['auth'])) continue;
            $bearer = ['authorization: Bearer ' . $accData['auth']];
            $rp = json_decode(Net::X($app.'/rp/claim-daily-rp', 'POST', null, null, $bearer, $host, Inf::$uagent) ?: '', true);
            if ($rp && ($rp['success'] ?? false) !== false) {
                $b->task1('', "claimed ({$rp['reward']} rp) for {$accData['mail']}");
            }
        }
        _rl('Enter to continue...');
        */
    } 
    elseif ($choice === '4') {
        // --- BALANCE / SEND SUBMENU ---
        while (true) {
            $b->show();
            $b->task1('ok', "Balance / Send Menu");
            Logger::X('info', "[1] Fetch all balance", true, true);
            Logger::X('info', "[2] Send once", true, true);
            Logger::X('info', "[3] Send bulk", true, true);
            Logger::X('info', "[4] Back to Main Menu", true, true);
            
            $rlFP = trim(_rl(' input: '));
            switch ($rlFP) {
                case '1': _getBal($jsonList, $host, $app); break;
                case '2': sendO($jsonList, $host, $app); break;
                case '3': sendB($jsonList, $host, $app); break;
                case '4': continue 2; // Kembali ke main_menu
                default: continue 2;
            }
        }
    } 
    elseif ($choice === '5') {
        foreach ($jsonList as &$account) {
            unset($account['auth'], $account['etag']);
        }
        unset($account);
        _put($mailJson, json_encode($jsonList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        Logger::X('info', "All Auth cleared from JSON.");
        _rl('Enter to continue...');
    } 
    elseif ($choice === '6') {
        die("Exited.\n");
    }
}

// ==========================================================
// FUNCTIONS
// ==========================================================

function _getBal($akun, $host, $app, $coinsOnly = false) {
    $b = Banner::getInstance();
    $filteredAkun = [];
    foreach ($akun as &$acc) {
        if (empty($acc['auth'])) continue;
        $b->task1('info', 'fetching balance...');
        $bearer = ['authorization: Bearer ' . $acc['auth']];
        $wallet = json_decode(Net::X($app.'/wallet/get-information', 'GET', null, null, $bearer, $host, Inf::$uagent) ?: '', true);
        #var_dump($wallet);
        
        $info = $wallet['data'] ?? null;
        if (empty($info)) continue;
        
        if ($coinsOnly) {
            $userBalances = [];
            foreach ($info['coin_balances'] as $coin) {
                $bal = (float)$coin['balance'];
                if ($bal > 0.00000100) $userBalances[$coin['coin']] = $bal;
            }
            if (!empty($userBalances)) {
                $acc['balances'] = $userBalances;
                $acc['total_balance'] = array_sum($userBalances);
                $filteredAkun[] = $acc;
            }
        } else {
            $saldo = (float)($info['statistics']['portfolio_value'] ?? 0);
            if ($saldo > 0) {
                $acc['balance'] = $saldo;
                $filteredAkun[] = $acc;
                $padding = max(0, 23 - strlen($acc['mail']));
                Logger::M(" " . $acc['mail'], false);
                Logger::X('info', sprintf(str_repeat(' ', $padding) . "[ balance: %-10.8f USD ]", $saldo), true, true);
            }
        }
    }
    unset($acc);
    if ($coinsOnly) return $filteredAkun;
    $b->task1('ok', 'all accounts fetched');
    _rl('Enter to continue...');
    return $filteredAkun;
}

function sendO($akun, $host, $app) {
    Logger::X('err', 'not stable yet');
    _rl('Enter to continue...');
}

function sendB($akun, $host, $app) {
    $b = Banner::getInstance();
    $b->show();
    $bal = _getBal($akun, $host, $app, true);
    $b->task1('info', 'INPUT RECEIVER EMAIL');
    $b->task2('err', 'USE WITH CAUTION, ALWAYS CHECK ADDRESS');
    
    if (!empty($bal)) {
        $tf = trim(_rl('INPUT RECEIVER: '));
        foreach ($bal as $acc) {
            if ($acc['mail'] === $tf) continue;
            foreach ($acc['balances'] as $_C => $_J) {
                $jmlh = rtrim(rtrim(sprintf("%.10f", (float)$_J), '0'), '.');
                $_H = ["authorization: Bearer " . $acc['auth']];
                $_P = ['coin' => $_C, 'amount' => $jmlh, '2fa_code' => '', 'user' => $tf];
                $send = json_decode(Net::X($app.'/transfer/send', 'POST', $_P, null, $_H, $host, Inf::$uagent, json: true) ?: '', true)['message'] ?? null;
                if (!empty($send)) {
                    Logger::M($acc['mail'], false);
                    Logger::X('info', $send, true, true);
                }
                _sle(5);
            }
        }
    }
    _rl('Enter to continue...');
}

function _getBer($akun, $cred, $api, $host, $autoOtp = false, $app = "https://api.faucetpay.io") {
    $needCaptcha = false;
    
    needcaptcha:
    $payload = [
        'user_email' => $akun['mail'],
        'password' => $akun['pass'],
        'fingerprint' => [
            'visitor_id' => $cred['visitor_id'] ?? '',
            'thumbmark' => $cred['thumbmark'] ?? '',
        ],
    ];
    
    if ($needCaptcha) {
        $payload['captcha_response'] = _getTKN($api, $cred)['token'] ?? '';
    }
    
    $loo = Net::X($host.'/app-api/session/login', 'POST', $payload, null, reff: $host.'/login', ua: Inf::$uagent, json: true, d: 1);
    $lo = json_decode(($loo['body'] ?: ''), true);
    $fpses = $loo['headers']['set-cookie'][0] ?? null;
    
    #var_dump($lo);
    
    if ($lo && ($lo['ok'] ?? false)) {
        logm($akun['mail'], false);
        logx('ok', $lo['message'] ?? 'Login OK', true, true);
        
        $tokn = Scraper::_jP($fpses, '/fp_session=([^;]+)/')[1][0] ?? null;
        
        if (($lo['needs_2fa'] ?? false) || !($lo['tfa_authorized'] ?? false)) {
            
            if ($autoOtp && _getOTP($tokn, $host, $akun['mail'])) {
                #logx('warn', "Skip {$akun['mail']}: Need 2FA and Auto OTP is OFF");
                return [$tokn, ''];
            }
            
            return false;
            
        }
        
        return [$tokn, ''];
    } else {
        if (isset($lo["captcha_required"]) && $lo["captcha_required"] === true && !empty(getenv('PROXY'))) {
            $needCaptcha = true;
            goto needcaptcha;
        }
        logx('err', "Login failed for {$akun['mail']}: " . ($lo['message'] ?? 'Unknown error'));
        return false;
    }
}

function _getTKN($api, $cred) {
    $cap = $api->run('faucetpay', [
        'sitekey' => 'a3760bfe5cf4254b2759c19fb2601667',
        'domain' => 'https://faucetpay.io',
        'proxy' => getenv("PROXY") ?? '',
    ])['done'] ?? '';
    
    if (str_starts_with($cap, 'cap')) {
        return ['token' => trim(str_replace('cap_res:', '', $cap))];
    }
    return [];
}

function _getOTP($coki, $host, $mail) {
    
    @unlink(Inf::$cookie);
    $head = Inf::netHead(['fp_session' => $coki]);
    $ott = json_decode(Net::X($host.'/app-api/account/get-2fa-type', 'GET', null, null, $head, reff: $host.'/verify-2fa', ua: Inf::$uagent)?: '', 1);
    
    for ($otry = 0; $otry < 3; $otry++) {
        
        if ($ott['ok']) {
            Net::X($host.'/app-api/account/resend-2fa-code', 'POST', head: $head, reff: $host.'/verify-2fa', ua: Inf::$uagent, json: 1);
            $input = _rl("please input ".$ott['tfa_type']." for $mail: ");
            
            $otv = json_decode(Net::X($host.'/app-api/session/verify-2fa', 'POST', ['code' => $input], null, $head, reff: $host.'/verify-2fa', ua: Inf::$uagent, json: 1)?: '', 1);
            #var_dump($otv);
            
            logm($mail, false);
            logx('info', ($otv['message'] ?? 'Unknown error'), true, true);
            
            if ($otv['ok']) return true;
            
        }
        
    } 
    
    
    return false;
    
}