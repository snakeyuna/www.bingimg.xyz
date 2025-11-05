<?php
/**
 * Bing壁纸数据代理 - 支持历史日期
 * 返回JSON格式的Bing壁纸数据
 */

// 设置响应头，允许跨域请求
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// 禁用错误显示，避免污染JSON输出
ini_set('display_errors', 0);
error_reporting(0);

// 获取请求参数
$daysAgo = isset($_GET['days']) ? intval($_GET['days']) : 0;
$daysAgo = max(0, min(7, $daysAgo)); // 限制在0-7之间，即今天到7天前

// 定义Bing图片API的URL地址
// format=js: 返回JSON格式数据
// idx: 获取指定天数前的数据(0:今天, 1:昨天, 2:前天...)
// n=1: 获取1张图片
// mkt=zh-CN: 中国市场区域设置
$url = "https://cn.bing.com/HPImageArchive.aspx?format=js&idx={$daysAgo}&n=1&mkt=zh-CN";

// 初始化cURL会话
$curl = curl_init();

// 设置cURL选项
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // 将响应保存到变量而不是直接输出

// 设置用户代理
curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

// 设置HTTP请求头
$headers = array(
   "Accept: application/json",
);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

// SSL证书验证设置（仅用于调试环境）
// 在生产环境中应该设置为true以确保安全
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 不验证主机名与证书是否匹配
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 不验证对等端证书

// 设置超时时间
curl_setopt($curl, CURLOPT_TIMEOUT, 10);

// 执行cURL请求，将响应内容保存到$resp变量
$resp = curl_exec($curl);

// 检查是否有错误发生
if(curl_error($curl)) {
    $error_msg = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    echo json_encode(array(
        'error' => true,
        'message' => 'cURL错误: ' . $error_msg,
        'http_code' => $http_code,
        'requested_days' => $daysAgo
    ));
    exit;
}

// 获取HTTP状态码
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

// 关闭cURL会话，释放资源
curl_close($curl);

// 检查HTTP状态码
if ($http_code !== 200) {
    echo json_encode(array(
        'error' => true,
        'message' => 'HTTP错误: ' . $http_code,
        'http_code' => $http_code,
        'requested_days' => $daysAgo
    ));
    exit;
}

// 检查响应是否为空
if (empty($resp)) {
    echo json_encode(array(
        'error' => true,
        'message' => 'API返回空响应',
        'requested_days' => $daysAgo
    ));
    exit;
}

// 解析JSON数据
$array = json_decode($resp);

// 检查JSON解析是否成功
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(array(
        'error' => true,
        'message' => 'JSON解析失败: ' . json_last_error_msg(),
        'raw_response' => substr($resp, 0, 200), // 只返回前200个字符用于调试
        'requested_days' => $daysAgo
    ));
    exit;
}

// 检查数据结构是否正确
if (!isset($array->images) || !is_array($array->images) || count($array->images) === 0) {
    echo json_encode(array(
        'error' => true,
        'message' => 'API返回的数据结构不正确',
        'requested_days' => $daysAgo
    ));
    exit;
}

// 构建完整的高清图片URL
$imgurl = 'https://cn.bing.com' . $array->images[0]->urlbase . '_UHD.jpg';

// 添加图片URL到返回数据中
$array->generatedImageUrl = $imgurl;
$array->requestedDays = $daysAgo;

// 输出获取到的JSON数据
echo json_encode($array, JSON_UNESCAPED_SLASHES);
?>