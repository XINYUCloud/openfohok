<?php
// ============================================================
// 极验验证码服务端校验
// ============================================================

/**
 * 校验极验参数
 * 参考：https://docs.geetest.com/captcha/apirefer/api/server
 *
 * @param array $post  前端提交的极验参数
 * @return array       返回 ['ok' => bool, 'msg' => string]
 */
function verifyCaptcha(array $post): array {
    $lot_number     = $post['lot_number'] ?? '';
    $captcha_output = $post['captcha_output'] ?? '';
    $pass_token     = $post['pass_token'] ?? '';
    $gen_time       = $post['gen_time'] ?? '';

    if (!$lot_number || !$captcha_output || !$pass_token || !$gen_time) {
        return ['ok' => false, 'msg' => '安全验证数据不完整，请重新完成验证'];
    }

    // 生成签名
    $sign_token = hash_hmac('sha256', $lot_number, CAPTCHA_KEY);

    $params = [
        'lot_number'     => $lot_number,
        'captcha_output' => $captcha_output,
        'pass_token'     => $pass_token,
        'gen_time'       => $gen_time,
        'sign_token'     => $sign_token,
    ];

    $url = CAPTCHA_URL . '?captcha_id=' . CAPTCHA_ID;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);

    if ($resp === false) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log('[Captcha] Geetest API 请求失败: ' . $error);
        return ['ok' => false, 'msg' => '安全验证服务异常，请稍后重试'];
    }

    curl_close($ch);

    $data = json_decode($resp, true);
    if (!$data) {
        error_log('[Captcha] Geetest API 返回非 JSON: ' . substr($resp, 0, 500));
        return ['ok' => false, 'msg' => '安全验证服务异常，请稍后重试'];
    }

    $result = $data['result'] ?? '';
    $reason = $data['reason'] ?? '';
    $code   = $data['code'] ?? '';

    if ($result === 'success') {
        return ['ok' => true, 'msg' => '验证通过'];
    }

    // 记录失败原因，便于排查
    error_log('[Captcha] Geetest 验证失败: result=' . $result . ' reason=' . $reason . ' code=' . $code . ' lot=' . substr($lot_number, 0, 16) . '...');

    // 根据 reason 给出更具体的提示
    if ($reason === 'pass_token expire' || strpos($reason, 'expire') !== false) {
        return ['ok' => false, 'msg' => '安全验证已过期，请重新完成验证'];
    }
    if ($reason === 'pass_token used' || strpos($reason, 'used') !== false) {
        return ['ok' => false, 'msg' => '安全验证数据已被使用，请重新完成验证'];
    }

    return ['ok' => false, 'msg' => '安全验证失败，请重试'];
}