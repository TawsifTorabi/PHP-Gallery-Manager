<?php

set_time_limit(120);
ini_set('memory_limit','512M');

function fetch_page($url){

$ch=curl_init();

curl_setopt_array($ch,[

CURLOPT_URL=>$url,
CURLOPT_RETURNTRANSFER=>true,
CURLOPT_FOLLOWLOCATION=>true,
CURLOPT_SSL_VERIFYPEER=>false,
CURLOPT_ENCODING=>'',
CURLOPT_TIMEOUT=>20,
CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36'

]);

$html=curl_exec($ch);

curl_close($ch);

return $html;

}

function absolute_url($base,$rel){

if(!$rel) return '';

if(parse_url($rel,PHP_URL_SCHEME)!='') return $rel;

if(substr($rel,0,2)=='//'){

$parts=parse_url($base);
return $parts['scheme'].":".$rel;

}

if($rel[0]=='/'){

$parts=parse_url($base);
return $parts['scheme'].'://'.$parts['host'].$rel;

}

return rtrim($base,'/').'/'.$rel;

}

function trim_url($url,$len=80){

if(strlen($url)<=$len) return $url;

return substr($url,0,$len).'...';

}

function get_sizes_async($urls){

$mh=curl_multi_init();
$chs=[];
$sizes=[];

foreach($urls as $u){

$ch=curl_init();

curl_setopt_array($ch,[

CURLOPT_URL=>$u,
CURLOPT_NOBODY=>true,
CURLOPT_RETURNTRANSFER=>true,
CURLOPT_TIMEOUT=>10,
CURLOPT_FOLLOWLOCATION=>true

]);

curl_multi_add_handle($mh,$ch);

$chs[$u]=$ch;

}

$running=null;

do{

curl_multi_exec($mh,$running);
curl_multi_select($mh);

}while($running>0);

foreach($chs as $url=>$ch){

$size=curl_getinfo($ch,CURLINFO_CONTENT_LENGTH_DOWNLOAD);

$sizes[$url]=$size>0?$size:0;

curl_multi_remove_handle($mh,$ch);
curl_close($ch);

}

curl_multi_close($mh);

return $sizes;

}

function detect_type($url){

$ext=strtolower(pathinfo(parse_url($url,PHP_URL_PATH),PATHINFO_EXTENSION));

$image=['jpg','jpeg','png','gif','webp','bmp','svg'];
$video=['mp4','webm','mov','mkv'];
$audio=['mp3','ogg','wav'];

if(in_array($ext,$image)) return "Image";
if(in_array($ext,$video)) return "Video";
if(in_array($ext,$audio)) return "Audio";

return "URL";

}

$results=[];
$title="";

if(isset($_POST['scrape'])){

$url=$_POST['url'];

$html=fetch_page($url);

preg_match('/<title>(.*?)<\/title>/i',$html,$t);

$title=$t[1]??"Scraped Gallery";

$media=[];

/* detect images */

preg_match_all('/https?:\/\/[^"\']+\.(jpg|jpeg|png|webp|gif|svg)/i',$html,$imgs);

foreach($imgs[0] as $i){

$media[$i]=detect_type($i);

}

/* detect video */

preg_match_all('/https?:\/\/[^"\']+\.(mp4|webm|mov|mkv)/i',$html,$vids);

foreach($vids[0] as $v){

$media[$v]=detect_type($v);

}

/* detect audio */

preg_match_all('/https?:\/\/[^"\']+\.(mp3|ogg|wav)/i',$html,$aud);

foreach($aud[0] as $a){

$media[$a]=detect_type($a);

}

/* detect links */

preg_match_all('/href=["\']([^"\']+)["\']/i',$html,$links);

foreach($links[1] as $l){

$l=absolute_url($url,$l);

$media[$l]="URL";

}

$sizes=get_sizes_async(array_keys($media));

foreach($media as $u=>$type){

$results[]= [

"url"=>$u,
"type"=>$type,
"size"=>$sizes[$u]??0

];

}

/* sort largest first */

usort($results,function($a,$b){

return $b['size']<=>$a['size'];

});

}

$gallery_images=[];
$gallery_desc="";

if(isset($_POST['create_gallery'])){

$title=$_POST['gallery_title'];

foreach($_POST['select'] as $i=>$url){

$type=$_POST['type'][$i];

if($type=="Image"){

$gallery_images[]=$url;

}else{

$gallery_desc.='<a href="'.$url.'" target="_blank">'.$url.'</a><br>';

}

}

}

?>

<!DOCTYPE html>
<html>
<head>

<title>Universal Media Scraper</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

.preview{

max-width:120px;
max-height:120px;

}

.gallery img{

max-width:200px;
margin:10px;

}

</style>

</head>

<body class="container mt-4">

<h3>Universal Media Scraper</h3>

<form method="post">

<div class="input-group mb-3">

<input type="text" name="url" class="form-control" placeholder="Enter URL">

<button name="scrape" class="btn btn-primary">Scrape</button>

</div>

</form>

<?php if(!empty($results)){ ?>

<form method="post">

<input type="hidden" name="gallery_title" value="<?php echo htmlspecialchars($title); ?>">

<table class="table table-bordered table-striped">

<thead>

<tr>

<th>Select</th>
<th>Preview</th>
<th>Type</th>
<th>Size</th>
<th>URL</th>

</tr>

</thead>

<tbody>

<?php foreach($results as $k=>$r){ ?>

<tr>

<td>

<input type="checkbox" name="select[<?php echo $k;?>]" value="<?php echo $r['url']; ?>">

<input type="hidden" name="type[<?php echo $k;?>]" value="<?php echo $r['type']; ?>">

</td>

<td>

<?php if($r['type']=="Image"){ ?>

<img src="<?php echo $r['url']; ?>" class="preview">

<?php }else{ echo "-"; } ?>

</td>

<td><?php echo $r['type']; ?></td>

<td>

<?php

if($r['size']>0){

echo round($r['size']/1024,1)." KB";

}else{

echo "-";

}

?>

</td>

<td>

<a href="<?php echo $r['url']; ?>" target="_blank">

<?php echo trim_url($r['url']); ?>

</a>

</td>

</tr>

<?php } ?>

</tbody>

</table>

<button name="create_gallery" class="btn btn-success">

Create Gallery

</button>

</form>

<?php } ?>

<?php if(!empty($gallery_images)){ ?>

<hr>

<h2><?php echo htmlspecialchars($title); ?></h2>

<div class="gallery">

<?php foreach($gallery_images as $g){ ?>

<img src="<?php echo $g; ?>">

<?php } ?>

</div>

<div>

<h4>Gallery Description</h4>

<?php echo $gallery_desc; ?>

</div>

<?php } ?>

</body>
</html>