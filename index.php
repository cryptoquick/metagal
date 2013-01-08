<?php

ini_set('memory_limit', '256M');

$baseImagesDir = '_images';
$baseThumbsDir = '_thumbs';
$thumbWidth = 200;
$thumbHeight = 100;

$albums = scandir($baseImagesDir);

// var_dump($albums);

$excluded = array(
	'.',
	'..',
	'.DS_Store'
);

foreach ($albums as $a) {
	$aDir = './' . $baseImagesDir . '/' . $a;
	if (is_dir($aDir) and !in_array($a, $excluded)) {
		// print '<p>' . $a . '</p>';
		$files = scandir($aDir);
		// var_dump($files);
		foreach ($files as $f) {
			$imgSrc = $aDir . '/' . $f;
			if (is_file($imgSrc) and !in_array($f, $excluded)) {
				echo $f . '<br>';
				/* Get Image Info */
				$imgInfo = getimagesize($imgSrc, $imgInfo);
				$iptcData = iptcparse($imgInfo['APP13']);
				// var_dump($iptcData);
				$metaTitle = $iptcData['2#005'];
				$metaInfo = $iptcData['2#120'];

				/* File Handling */
				$thumbsDir = './' . $baseThumbsDir . '/' . $a;
				$thumbDest = $thumbsDir . '/' . $f;

				if (is_file($thumbDest)) {
					echo unlink($thumbDest) ? 'deleting previous thumb successfully<br>' : 'could not delete previous thumb<br>';
				}

				if (!is_dir($thumbsDir)) {
					$mkdirSuccess = mkdir($thumbsDir, 0777) ? 'mkdir successful<br>' : 'mkdir unsuccessful<br>';
				}

				/* Make Image Resources */
				$thumbRes = imagecreatetruecolor($thumbWidth, $thumbHeight);

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
					echo 'unsupported image type<br>';
				}

				echo $thumbsDir . "<br>\n";

				/* Calculate Thumb Dimensions */
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

				/* Resize Photo into Thumb */
				$resizeSuccess = imagecopyresampled($thumbRes, $imgRes, 0, 0, $imgX, $imgY, $thumbWidth, $thumbHeight, $imgCropWidth, $imgCropHeight);

				if ($imgInfo[2] == 1) { // jpeg
					imagegif($thumbRes, $thumbDest);
				}
				else if ($imgInfo[2] == 2) { // jpeg
					imagejpeg($thumbRes, $thumbDest);
				}
				else if ($imgInfo[2] == 3) {
					imagepng($thumbRes, $thumbDest);
				}
				else {
					echo 'unsupported image type<br>';
				}

				imagedestroy($thumbRes);
				imagedestroy($imgRes);

				echo $resizeSuccess ? 'resize successful<br>' : 'resize unsuccessful<br>';
				echo '<br>';
			}
		}
	}
}

?>