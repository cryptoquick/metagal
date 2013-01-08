<?php

ini_set('memory_limit', '256M');

$baseImagesDir = './_images';
$baseThumbsDir = './_thumbs';
$thumbWidth = 200;
$thumbHeight = 100;
$thumbMinSize = 150;

$albums = scandir($baseImagesDir);
$excluded = array (
	'.',
	'..',
	'.DS_Store'
);
$status = array (
	"processingStatuses" => array(
		"Thumbs Deleted Successfully|SUCCESS" => 0,
		"Thumbs Deleted Unsuccesfully|FAIL" => 0,
		"Directories Created Successfully|SUCCESS" => 0,
		"Directories Created Unsuccesfully|FAIL" => 0,
		"Unsupported Image Types|FAIL" => 0,
		"Images Successfully Resized|SUCCESS" => 0,
		"Images Unsuccessfully Resized|FAIL" => 0
	),
	"totalImages" => 0,
	"finished" => false
);
$response = array(
	"baseImagesDirectory" => $baseImagesDir,
	"baseThumbsDirectory" => $baseThumbsDir,
	"relPaths" => array()
);

if (!is_dir($baseThumbsDir)) {
	mkdir($baseThumbsDir, 0777);
}

function center_thumb_dims ($imgInfo) {
	/* Calculate Thumb Dimensions */
	/* Unfinished, returns incorrect aspect ratio... */
	$imgWidth = $imgInfo[0];
	$imgHeight = $imgInfo[1];

	$imgLargestDim = $imgWidth > $imgHeight ? $imgWidth : $imgHeight;
	$imgSmallestDim = $imgWidth < $imgHeight ? $imgWidth : $imgHeight;
	$imgAspectRatio = $imgLargestDim / $imgSmallestDim;

	$thumbLargestDim = $thumbWidth > $thumbHeight ? $thumbWidth : $thumbHeight;
	$thumbSmallestDim = $thumbWidth < $thumbHeight ? $thumbWidth : $thumbHeight;
	$thumbAspectRatio = $thumbLargestDim / $thumbSmallestDim;

	$imgX = $imgWidth / 2 - $imgSmallestDim / 2;
	$imgY = $imgHeight / 2 - $imgSmallestDim / 2;

	$imgCropWidth = $imgWidth / $thumbAspectRatio;
	$imgCropHeight = $imgHeight / $thumbAspectRatio;

	return array (
		"x" => $imgX,
		"y" => $imgY,
		"tw" => $thumbWidth,
		"th" => $thumbHeight,
		"icw" => $imgCropWidth,
		"ich" => $imgCropHeight
	);
}

/* For use with min- css attributes */
function css_thumb_dims ($imgInfo, $thumbMinSize) {
	$imgWidth = $imgInfo[0];
	$imgHeight = $imgInfo[1];

	$imgLargestDim = $imgWidth > $imgHeight ? $imgWidth : $imgHeight;
	$imgSmallestDim = $imgWidth < $imgHeight ? $imgWidth : $imgHeight;
	$imgAspectRatio = $imgLargestDim / $imgSmallestDim;

	$thumbMinWidth = $imgWidth < $imgHeight ? $thumbMinSize : $thumbMinSize * $imgAspectRatio;
	$thumbMinHeight = $imgHeight < $imgWidth ? $thumbMinSize : $thumbMinSize * $imgAspectRatio;

	return array (
		"x" => 0,
		"y" => 0,
		"tw" => round($thumbMinWidth),
		"th" => round($thumbMinHeight),
		"icw" => $imgWidth,
		"ich" => $imgHeight
	);
}

foreach ($albums as $a) {
	$aDir = $baseImagesDir . '/' . $a;
	if (is_dir($aDir) and !in_array($a, $excluded)) {
		$files = scandir($aDir);
		foreach ($files as $f) {
			$imgSrc = $aDir . '/' . $f;
			if (is_file($imgSrc) and !in_array($f, $excluded)) {
				/* Get Image Info */
				$imgInfo = getimagesize($imgSrc, $iptcData);
				$iptc = iptcparse($iptcData['APP13']);
				$metaTitle = $iptc['2#005'][0];
				$metaInfo = $iptc['2#120'][0];

				/* File Handling */
				$thumbsDir = $baseThumbsDir . '/' . $a;
				$thumbDest = $thumbsDir . '/' . $f;

				// array_push($response["relPaths"][$a], $f);

				if (is_file($thumbDest)) {
					if(unlink($thumbDest)) {
						$status["processingStatuses"]["Thumbs Deleted Successfully|SUCCESS"]++;
					} 
					else {
						$status["processingStatuses"]["Thumbs Deleted Unsuccessfully|FAIL"]++;
					}
				}

				if (!is_dir($thumbsDir)) {
					if(mkdir($thumbsDir, 0777)) {
						$status["processingStatuses"]["Directories Created Successfully|SUCCESS"]++;
					}
					else {
						$status["processingStatuses"]["Directories Created Unsuccessfull|FAIL"]++;
					}
				}

				/* Calculate Thumbnail Dimensions */
				$thumb_dims = css_thumb_dims($imgInfo, $thumbMinSize);
				var_dump($thumb_dims);

				/* Add to Response */
				if (!$response["relPaths"][$a])
					$response["relPaths"][$a] = array();

				$response["relPaths"][$a][$f] = array($metaTitle, $metaInfo, $thumb_dims['tw'], $thumb_dims['th']);

				/* Make Image Resources */
				if ($thumbMinSize) {
					$thumbRes = imagecreatetruecolor($thumb_dims['tw'], $thumb_dims['th']);
				}
				else {
					$thumbRes = imagecreatetruecolor($thumbWidth, $thumbHeight);
				}

				if ($imgInfo[2] == 1) { // jpeg
					$imgRes = imagecreatefromgif($imgSrc);
				}
				else if ($imgInfo[2] == 2) { // jpeg
					$imgRes = imagecreatefromjpeg($imgSrc);
				}
				else if ($imgInfo[2] == 3) {
					$imgRes = imagecreatefrompng($imgSrc);
				}
				else {
					$status["processingStatuses"]["Unsupported Image Types|FAIL"]++;
					return 0;
				}

				/* Resize Photo into Thumb */
				$resizeSuccess = imagecopyresampled(
					$thumbRes, $imgRes,
					0, 0, $thumb_dims["x"], $thumb_dims["y"],
					$thumb_dims["tw"], $thumb_dims["th"], $thumb_dims["icw"], $thumb_dims["ich"]
				);

				if ($imgInfo[2] == 1) { // jpeg
					imagegif($thumbRes, $thumbDest);
				}
				else if ($imgInfo[2] == 2) { // jpeg
					imagejpeg($thumbRes, $thumbDest);
				}
				else if ($imgInfo[2] == 3) {
					imagepng($thumbRes, $thumbDest);
				}

				imagedestroy($thumbRes);
				imagedestroy($imgRes);

				if($resizeSuccess) {
					$status["processingStatuses"]["Images Successfully Resized|SUCCESS"]++;
				}
				else {
					$status["processingStatuses"]["Images Unsuccessfully Resized|FAIL"]++;
				}
			}
		}
	}
}

/* Output */
var_dump($status);
var_dump($response);

echo "JSON: " . json_encode($response);
echo "<br>";
echo "Length: " . strlen(json_encode($response));

echo "<style>\n";
echo "\tdiv a {\n";
echo "\t\tdisplay:block;\n";
echo "\t\tfloat:left;\n";
echo "\t\twidth:150px;\n";
echo "\t\theight:150px;\n";
echo "\t\tline-height:150px;\n";
echo "\t\toverflow:hidden;\n";
echo "\t\tposition:relative;\n";
echo "\t\tz-index:1;\n";
echo "\t}\n\n";

echo "\tdiv a img {\n";
echo "\t\tfloat:left;\n";
echo "\t\tposition:absolute;\n";
echo "\t}\n";
echo "</style>\n";

/* Generate Example HTML (ideally created with JavaScript instead) */
echo "<div>";
foreach ($response['relPaths'] as $album => $ra) {
	foreach ($ra as $file => $rf) {
		echo "\t<a href=\"" . $response['baseImagesDirectory'] . '/' . $album . '/' . $file . "\">\n";
		echo "\t\t<img style=\"left:-" . ($rf[2] - 150) / 2 . "px; top:-" . ($rf[3] - 150) / 2 . "px;\" src=\"" . $response['baseThumbsDirectory'] . '/' . $album . '/' . $file . "\" title=\"" . $rf[0] . "\n" . $rf[1] . "\">\n";
		echo "\t</a>\n";
	}
}
echo "</div>";
?>