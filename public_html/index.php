<?php
namespace ellsif;

require '../vendor/autoload.php';

$baseurl = ($_POST['baseurl']) ?? '';

$num = 1;
$score = 0;
$images = 0;
if ($baseurl) {
    $scout = new WebPageScout();
    $result = $scout->scout($baseurl);
    foreach($result as $url => $data) {
        $score += intval($data['bodySize']);
        $images += count($data['imageList'] ?? []);
    }
} else {
    $result = [];
}
?>
<html>
    <head>
        <link href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <section class="container-fluid">
            <h1>りにゅーあるみつもるくん</h1>
            <p>
                <form class="form-inline" method="post">
                    対象URL：<input class="form-control" style="width: 400px;" name="baseurl" type="text" value="<?php echo $baseurl; ?>">
                    <input type="submit" value="みつもる" class="btn btn-primary" type="submit">
                </form>
            </p>
            <p>ページ数：<?php echo count($result); ?></p>
            <p>総画像数：<?php echo $images; ?></p>
            <p>総合規模：<?php echo $score; ?></p>
            <table class="table table-bordered">
                <tr>
                    <th>No</th>
                    <th>URL</th>
                    <th>ページタイトル</th>
                    <th>画像の数</th>
                    <th>規模</th>
                </tr>
                <?php foreach($result as $url => $data): ?>
                <tr>
                    <th><?php echo $num++;?></th>
                    <th><a target="_blank" href="<?php echo $url ?>"><?php echo $url ?></a></th>
                    <th><?php echo $data['title'] ?? '' ?></th>
                    <th><?php echo count($data['imageList'] ?? []) ?></th>
                    <th><?php echo $data['bodySize'] ?? 0; ?></th>
                </tr>
                <?php endforeach; ?>
            </table>
        </section>
    </body>
</html>
