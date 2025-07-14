<?php
//免费开源，请勿收费转载
//作者:陈星燎
//个人博客blog.xoun.cn
require_once(dirname(__FILE__) . '/config.inc.php');
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

$apiUrl = 'http://api.suxun.site/api/sixs?type=json';
$response = file_get_contents($apiUrl);
$data = json_decode($response, true);

if ($data['code']!== '200') {
    die('获取 API 数据失败。');
}

$weekdayMap = array(
    'Sunday' => '星期日',
    'Monday' => '星期一',
    'Tuesday' => '星期二',
    'Wednesday' => '星期三',
    'Thursday' => '星期四',
    'Friday' => '星期五',
    'Saturday' => '星期六'
);

$useRemoteImage = false; // 可以改为true 为远程图片(api里返回的图片) false为本地

$localImageBasePath = '/usr/uploads/60s/'; // 本地图片路径
$localImages = array(
    'Sunday' => '7.png',
    'Monday' => '1.png',
    'Tuesday' => '2.png',
    'Wednesday' => '3.png',
    'Thursday' => '4.png',
    'Friday' => '5.png',
    'Saturday' => '6.png'
);

try {
    $db = Typecho_Db::get();
    $db->query("SET NAMES 'utf8mb4'");

    $categoryName = '新闻早报';//设置为你的分类名称

    $categoryRow = $db->fetchRow($db->select('mid')
        ->from('table.metas')
        ->where('type =?', 'category')
        ->where('name =?', $categoryName));
    $categoryId = isset($categoryRow['mid'])? $categoryRow['mid'] : 0;

    if (!$categoryId) {
        die("无法找到指定分类的 ID。");
    }

    $todaySlug = date('Ymd');
    $exists = $db->fetchRow($db->select('cid')
        ->from('table.contents')
        ->where('slug =?', $todaySlug)
        ->limit(1));

    if ($exists) {
        die("今日文章已发布，无需重复发布。");
    }
    $nextCid = $db->fetchObject($db->select(['MAX(cid)' => 'num'])
        ->from('table.contents'))->num + 1;
    $englishWeekday = date('l');
    $chineseWeekday = isset($weekdayMap[$englishWeekday])? $weekdayMap[$englishWeekday] : $englishWeekday;
    $date = date('Y 年 n 月 j 日'). '，'. $chineseWeekday;

    $title = $date. '，每日 60 秒读懂世界';//标题可以改为你喜欢的 $date 是可以用的变量这个是日期

    if ($useRemoteImage) {
        $imageTag = '<img src="'.$data['head_image'].'"><br>';
    } else {
        $localImageFile = isset($localImages[$englishWeekday])? $localImages[$englishWeekday] : 'default.jpg';
        $localImageUrl = $localImageBasePath. $localImageFile;
        $imageTag = '<img src="'.$localImageUrl.'"><br>';
    }

    $content = '<div style="text-align: center; font-size: 16px; color: #666; margin: 15px 0; padding: 8px;background: #f8f8f8; border-radius: 4px;">💡每天一分钟，知晓天下事。💡</div>' . $imageTag;
    foreach ($data['news'] as $newsItem) {
    $content.= mb_convert_encoding($newsItem, 'UTF-8', 'auto'). '<br>';
    }

    $content .= '<br>' . mb_convert_encoding($data['weiyu'], 'UTF-8', 'auto') . '<div style="text-align: center; font-size: 16px; color: #666; margin: 15px 0; padding: 8px;background: #f8f8f8; border-radius: 4px;">📌 点击下方「喜欢」支持我们</div><br>';
    $slug = $todaySlug;
    $db->query('START TRANSACTION');
    try {
        $db->query($db->insert('table.contents')
            ->rows(array(
                'cid' => $nextCid,
                'title' => mb_convert_encoding($title, 'UTF-8', 'auto'),
               'slug' => $slug,
                'created' => time(),
               'modified' => time(),
                'text' => $content,
                'order' => 0,
                'authorId' => 1,
                'type' => 'post',
               'status' => 'publish',
                'commentsNum' => 0,
                'allowComment' => 1,
                'allowPing' => 1,
                'allowFeed' => 1,
                'parent' => 0
            )));
        $db->query($db->insert('table.relationships')
            ->rows(array(
                'cid' => $nextCid,
               'mid' => $categoryId
            )));
        $db->query($db->update('table.metas')
            ->expression('count', 'count + 1')
            ->where('mid =?', $categoryId));
        $db->query('COMMIT');

        echo "文章发布成功！文章标题: ". $title;

    } catch (Exception $e) {
        $db->query('ROLLBACK');
        die("发布文章时出错: ". $e->getMessage());
    }

} catch (Exception $e) {
    die("初始化时出错: ". $e->getMessage());
}
?>
