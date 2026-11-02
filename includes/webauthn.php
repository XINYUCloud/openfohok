<?php
// ============================================================
// WebAuthn (FIDO2) 通行密钥辅助函数
// ============================================================

// RP 配置
define('WEBAUTHN_RP_ID', 'fohok.xin');
define('WEBAUTHN_RP_NAME', '文谶驿站');
define('WEBAUTHN_TIMEOUT', 60000);
define('WEBAUTHN_MAX_CREDENTIALS', 1);

// ── Base64URL 编解码 ──────────────────────────────────────
function b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

// ── 生成随机 Challenge ────────────────────────────────────
function generateChallenge(int $length = 32): string {
    return openssl_random_pseudo_bytes($length);
}

// ── CBOR 解码器（最小实现，支持 WebAuthn 所需类型）─────────
function cbor_decode(string $data, int &$offset = 0, int $maxDepth = 10) {
    if ($maxDepth <= 0) throw new \RuntimeException('CBOR: max depth exceeded');
    $byte = ord($data[$offset]);
    $major = ($byte >> 5) & 0x07;
    $info  = $byte & 0x1F;
    $offset++;

    $value = $info;
    if ($info < 24) {
        // 直接值
    } elseif ($info === 24) {
        $value = ord($data[$offset++]);
    } elseif ($info === 25) {
        $value = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
    } elseif ($info === 26) {
        $value = unpack('N', substr($data, $offset, 4))[1];
        $offset += 4;
    } elseif ($info === 27) {
        $hi = unpack('N', substr($data, $offset, 4))[1];
        $lo = unpack('N', substr($data, $offset + 4, 4))[1];
        $value = ($hi << 32) | $lo;
        $offset += 8;
    } elseif ($info === 31) {
        $value = null; // 不定长
    }

    switch ($major) {
        case 0: // 无符号整数
            return $value;
        case 1: // 负整数
            return -1 - $value;
        case 2: // 字节串
            // 不定长字节串
            if ($info === 31) {
                $bytes = '';
                while ($data[$offset] !== "\xff" || $offset >= strlen($data)) {
                    $bytes .= cbor_decode($data, $offset, $maxDepth - 1);
                }
                $offset++; // 跳过终止符
                return $bytes;
            }
            $bytes = substr($data, $offset, $value);
            $offset += $value;
            return $bytes;
        case 3: // 文本串
            $text = substr($data, $offset, $value);
            $offset += $value;
            return $text;
        case 4: // 数组
            $arr = [];
            $end = ($info === 31) ? strlen($data) : $offset + $value;
            if ($info === 31) {
                while ($offset < strlen($data) && $data[$offset] !== "\xff") {
                    $arr[] = cbor_decode($data, $offset, $maxDepth - 1);
                }
                $offset++; // 跳过终止符
            } else {
                while ($offset < $end) {
                    $arr[] = cbor_decode($data, $offset, $maxDepth - 1);
                }
            }
            return $arr;
        case 5: // 映射
            $map = [];
            $end = ($info === 31) ? strlen($data) : $offset + $value;
            if ($info === 31) {
                while ($offset < strlen($data) && $data[$offset] !== "\xff") {
                    $k = cbor_decode($data, $offset, $maxDepth - 1);
                    $v = cbor_decode($data, $offset, $maxDepth - 1);
                    $map[$k] = $v;
                }
                $offset++;
            } else {
                $pairs = $value;
                for ($i = 0; $i < $pairs; $i++) {
                    $k = cbor_decode($data, $offset, $maxDepth - 1);
                    $v = cbor_decode($data, $offset, $maxDepth - 1);
                    $map[$k] = $v;
                }
            }
            return $map;
        case 7: // 简单类型/浮点
            if ($info === 20) return false;
            if ($info === 21) return true;
            if ($info === 22) return null;
            if ($info === 25) {
                $v = unpack('G', substr($data, $offset - 2, 8))[1];
                $offset += 6;
                return $v;
            }
            return $value;
        default:
            return null;
    }
}

// ── 解析 authData ─────────────────────────────────────────
function parseAuthData(string $authData): array {
    $rpIdHash    = substr($authData, 0, 32);
    $flags       = ord($authData[32]);
    $signCount   = unpack('N', substr($authData, 33, 4))[1];
    $pos         = 37;

    $result = [
        'rpIdHash'  => $rpIdHash,
        'flags'     => $flags,
        'signCount' => $signCount,
        'hasAttestedCredData' => (bool)($flags & 0x40),
        'hasExtensions'       => (bool)($flags & 0x80),
    ];

    if ($result['hasAttestedCredData']) {
        $aaguid = substr($authData, $pos, 16);
        $pos += 16;
        $credIdLen = unpack('n', substr($authData, $pos, 2))[1];
        $pos += 2;
        $credId = substr($authData, $pos, $credIdLen);
        $pos += $credIdLen;
        $result['aaguid']       = $aaguid;
        $result['credentialId'] = $credId;
        $result['coseKey']      = substr($authData, $pos);
    }

    return $result;
}

// ── COSE 密钥转 PEM 公钥（ES256 / P-256）─────────────────
function coseKeyToPem(array $coseKey): ?string {
    $kty = $coseKey[1] ?? 0;  // 1=OKP, 2=EC2, 3=RSA
    $alg = $coseKey[3] ?? 0;  // -7=ES256, -257=RS256
    $crv = $coseKey[-1] ?? 0; // 1=P-256

    if ($kty === 2 && $alg === -7 && $crv === 1) {
        // ES256 / P-256
        $x = $coseKey[-2] ?? ''; // x coordinate
        $y = $coseKey[-3] ?? ''; // y coordinate

        if (strlen($x) !== 32 || strlen($y) !== 32) return null;

        // 构建未压缩的 EC 公钥：04 || x || y
        $der = "\x04" . $x . $y;

        // 构建 PEM
        $pem  = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split(base64_encode(
            "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01" .
            "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00" . $der
        ), 64, "\n");
        $pem .= "-----END PUBLIC KEY-----";
        return $pem;
    }

    if ($kty === 3 && ($alg === -257 || $alg === -37)) {
        // RS256 / PS256
        $n = $coseKey[-1] ?? '';
        $e = $coseKey[-2] ?? '';
        if (empty($n) || empty($e)) return null;

        $seq = asn1_integer($n) . asn1_integer($e);
        $der = "\x30" . asn1_len(strlen($seq)) . $seq;
        $pem  = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split(base64_encode(
            "\x30" . asn1_len(strlen($der)) . $der
        ), 64, "\n");
        $pem .= "-----END PUBLIC KEY-----";
        return $pem;
    }

    return null;
}

function asn1_len(int $len): string {
    if ($len < 128) return chr($len);
    $bytes = '';
    $tmp = $len;
    while ($tmp > 0) { $bytes = chr($tmp & 0xFF) . $bytes; $tmp >>= 8; }
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function asn1_integer(string $n): string {
    // 去掉前导零
    $n = ltrim($n, "\x00");
    if ($n === '') $n = "\x00";
    // 如果最高位为 1，需要加前导零
    if (ord($n[0]) & 0x80) $n = "\x00" . $n;
    return "\x02" . asn1_len(strlen($n)) . $n;
}

// ── 签名验证 ──────────────────────────────────────────────
function verifySignature(string $pemPubKey, string $data, string $signature): array {
    $debug = [];

    $key = openssl_pkey_get_public($pemPubKey);
    if (!$key) return ['ok' => false, 'msg' => 'PEM 公钥加载失败', 'debug' => substr($pemPubKey, 0, 80)];

    $details = openssl_pkey_get_details($key);
    if ($details === false) return ['ok' => false, 'msg' => '无法获取密钥详情'];

    $keyType = $details['type'];
    $debug['keyType'] = $keyType;
    $debug['sigLen'] = strlen($signature);
    $debug['dataLen'] = strlen($data);

    // OPENSSL_KEYTYPE_EC = 3
    if ($keyType === 3) {  // EC key (ES256)
        $derSig = ecSignatureToDer($signature);
        if ($derSig === null) {
            return ['ok' => false, 'msg' => 'ECDSA 签名格式不支持', 'debug' => $debug];
        }
        $result = openssl_verify($data, $derSig, $key, OPENSSL_ALGO_SHA256);
        $debug['openssl_result'] = $result;
        $debug['openssl_error'] = openssl_error_string();
    } else {
        // RSA (RS256)
        $result = openssl_verify($data, $signature, $key, OPENSSL_ALGO_SHA256);
        $debug['openssl_result'] = $result;
        $debug['openssl_error'] = openssl_error_string();
    }

    $ok = ($result === true || $result === 1);
    return ['ok' => $ok, 'msg' => $ok ? '' : '签名不匹配', 'debug' => $debug];
}

// ── ECDSA 签名归一化为 DER 格式 ──────────────────────────
// 自动检测输入格式：
//   - 64 字节 raw r||s → 转为 DER
//   - 已是 DER 格式（以 0x30 开头）→ 直接返回
function ecSignatureToDer(string $sig): ?string {
    $len = strlen($sig);

    // 已是 DER 格式（0x30 = SEQUENCE 标签）
    if ($len > 2 && ord($sig[0]) === 0x30) {
        return $sig;
    }

    // raw r||s 格式（P-256 曲线 = 64 字节）
    if ($len === 64) {
        return ecdsaSigToDer($sig);
    }

    return null;
}

// ── ECDSA 原始签名转 DER 格式 ─────────────────────────────
function ecdsaSigToDer(string $sig): ?string {
    $len = strlen($sig);
    if ($len !== 64) return null;
    $r = substr($sig, 0, 32);
    $s = substr($sig, 32, 32);
    $rDer = asn1_integer($r);
    $sDer = asn1_integer($s);
    $seq = $rDer . $sDer;
    return "\x30" . asn1_len(strlen($seq)) . $seq;
}

// ── 构建 credentialCreationOptions ────────────────────────
function buildCreateOptions(int $userId, string $userName, string $userDisplayName): array {
    $challenge = generateChallenge();
    $challengeB64 = b64url_encode($challenge);

    // 存储 challenge 到 session
    $_SESSION['webauthn_challenge'] = $challenge;
    $_SESSION['webauthn_action'] = 'register';

    return [
        'publicKey' => [
            'rp' => [
                'id'   => WEBAUTHN_RP_ID,
                'name' => WEBAUTHN_RP_NAME,
            ],
            'user' => [
                'id'          => b64url_encode((string)$userId),
                'name'        => $userName,
                'displayName' => $userDisplayName,
            ],
            'challenge'        => $challengeB64,
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'timeout'            => WEBAUTHN_TIMEOUT,
            'excludeCredentials' => [],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'userVerification'        => 'preferred',
                'residentKey'             => 'preferred',
            ],
            'attestation' => 'none',
        ],
    ];
}

// ── 构建 credentialRequestOptions ─────────────────────────
function buildGetOptions(array $allowCredentials = []): array {
    $challenge = generateChallenge();
    $challengeB64 = b64url_encode($challenge);

    $_SESSION['webauthn_challenge'] = $challenge;
    $_SESSION['webauthn_action'] = 'authenticate';

    $opts = [
        'publicKey' => [
            'challenge'        => $challengeB64,
            'rpId'             => WEBAUTHN_RP_ID,
            'timeout'          => WEBAUTHN_TIMEOUT,
            'userVerification' => 'preferred',
        ],
    ];

    if (!empty($allowCredentials)) {
        $opts['publicKey']['allowCredentials'] = $allowCredentials;
    }

    return $opts;
}

// ── 验证注册响应 ──────────────────────────────────────────
function verifyAttestation(array $clientDataJSON, string $attestationObjectB64, string $clientDataJSONRaw): ?array {
    // 1. 验证 challenge
    $challenge = $_SESSION['webauthn_challenge'] ?? '';
    if (empty($challenge)) return null;

    $clientData = json_decode(b64url_decode($clientDataJSONRaw), true);
    if (!$clientData || ($clientData['type'] ?? '') !== 'webauthn.create') return null;

    $clientChallenge = b64url_decode($clientData['challenge'] ?? '');
    if ($clientChallenge !== $challenge) return null;

    // 验证 origin
    if (!str_starts_with($clientData['origin'] ?? '', 'https://' . WEBAUTHN_RP_ID)) {
        // 也允许 localhost
        if (!str_starts_with($clientData['origin'] ?? '', 'http://localhost')) {
            return null;
        }
    }

    // 2. 解析 attestationObject
    $attObj = cbor_decode(b64url_decode($attestationObjectB64));
    $authData = parseAuthData($attObj['authData'] ?? '');

    // 3. 验证 RP ID hash
    $expectedHash = hash('sha256', WEBAUTHN_RP_ID, true);
    if ($authData['rpIdHash'] !== $expectedHash) return null;

    // 4. 验证用户在场标志
    if (!($authData['flags'] & 0x01)) return null; // UP

    // 5. 提取公钥
    if (!$authData['hasAttestedCredData']) return null;
    $coseKey = cbor_decode($authData['coseKey']);
    $pem = coseKeyToPem($coseKey);
    if (!$pem) return null;

    return [
        'credentialId' => b64url_encode($authData['credentialId']),
        'publicKey'    => $pem,
        'signCount'    => $authData['signCount'],
    ];
}

// ── 验证认证响应 ──────────────────────────────────────────
function verifyAssertion(array $credential, string $clientDataJSONRaw, string $authenticatorDataB64, string $signatureB64, string $userHandleB64): array {
    // 1. 验证 challenge
    $challenge = $_SESSION['webauthn_challenge'] ?? '';
    if (empty($challenge)) return ['ok' => false, 'msg' => '会话已过期，请重新验证'];

    $clientData = json_decode(b64url_decode($clientDataJSONRaw), true);
    if (!$clientData) return ['ok' => false, 'msg' => '客户端数据解析失败'];
    if (($clientData['type'] ?? '') !== 'webauthn.get') return ['ok' => false, 'msg' => '类型不匹配'];

    $clientChallenge = b64url_decode($clientData['challenge'] ?? '');
    if ($clientChallenge !== $challenge) return ['ok' => false, 'msg' => '挑战值不匹配'];

    // 验证 origin
    $origin = $clientData['origin'] ?? '';
    if (!str_starts_with($origin, 'https://' . WEBAUTHN_RP_ID) && !str_starts_with($origin, 'http://localhost')) {
        return ['ok' => false, 'msg' => '来源验证失败: ' . $origin];
    }

    // 2. 验证 RP ID hash
    $authDataRaw = b64url_decode($authenticatorDataB64);
    $authData = parseAuthData($authDataRaw);
    $expectedHash = hash('sha256', WEBAUTHN_RP_ID, true);
    if ($authData['rpIdHash'] !== $expectedHash) return ['ok' => false, 'msg' => 'RP ID 哈希不匹配'];

    // 3. 验证用户在场
    if (!($authData['flags'] & 0x01)) return ['ok' => false, 'msg' => '用户在场标志未设置'];

    // 4. 验证签名
    $clientDataHash = hash('sha256', b64url_decode($clientDataJSONRaw), true);
    $signedData = $authDataRaw . $clientDataHash;
    $signature = b64url_decode($signatureB64);

    $sigResult = verifySignature($credential['public_key'], $signedData, $signature);
    if (!$sigResult['ok']) {
        return ['ok' => false, 'msg' => '签名验证失败: ' . $sigResult['msg'], 'debug' => $sigResult['debug'] ?? []];
    }

    return ['ok' => true];
}