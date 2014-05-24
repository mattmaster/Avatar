<?php

function downloadPapers($itemId, $paperSizes = array(60, 88, 120)){
	foreach($paperSizes as $paperSize){
		$avatarPaperUri = sprintf('paper/%d/%d.png', $paperSize, $itemId);
		$avatarPaperUrl = 'http://media1.clubpenguin.com/avatar/' . $avatarPaperUri;
		$paperCurl = curl_init($avatarPaperUrl);
		curl_setopt($paperCurl, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($paperCurl, CURLOPT_RETURNTRANSFER, true);
		$imageData = curl_exec($paperCurl);
		$statusCode = curl_getinfo($paperCurl, CURLINFO_HTTP_CODE);
		curl_close($paperCurl);
		if($statusCode == 200){
			file_put_contents($avatarPaperUri, $imageData);
		} else {
			return false;
		}
	}
}

function cachePaper($itemId, $paperSize){
	$downloadStatus = downloadPapers($itemId);
	
	if($downloadStatus !== false){
		$paperImage = imagecreatefrompng(sprintf('paper/%d/%d.png', $paperSize, $itemId));
		return $paperImage;
	} else {
		return false;
	}
}

function returnPaperResource($itemId, $paperSize = 120){
	$avatarUnbiasUri = sprintf('paper/%d/%d.png', $paperSize, $itemId);
	$paperImage = file_exists($avatarUnbiasUri) ? imagecreatefrompng($avatarUnbiasUri) : cachePaper($itemId, $paperSize);
	
	return $paperImage;
}

$validPaperSizes = array(60, 88, 120);
$defaultPaperSize = 120;

$playerSwid = $_GET['swid'];
$avatarPaperSize = $_GET['size'];

if(!in_array($avatarPaperSize, $validPaperSizes)){
	$avatarPaperSize = $defaultPaperSize;
}

$connectionString = 'mysql:dbname=kitsune;host=127.0.0.1';
$databaseUser = 'kitsune';
$databasePass = 'zMTFpF23yZcYprRj';

try {
	$database = new PDO($connectionString, $databaseUser, $databasePass);
} catch(PDOException $pdoException){
	echo $pdoException->getMessage(), die();
}

$playerQuery = 'SELECT ID FROM `penguins` WHERE SWID = :Swid';
$playerStatement = $database->prepare($playerQuery);
$playerStatement->bindValue(':Swid', $playerSwid);
$playerStatement->execute();
$rowCount = $playerStatement->rowCount();
$playerStatement->closeCursor();

if($rowCount < 1){
	echo 'Player doesn\'t exist', die();
}

$clothingQuery = 'SELECT Color, Head, Face, Body, Neck, Hand, Feet, Photo, Flag FROM `penguins` WHERE SWID = :Swid';
$clothingStatement = $database->prepare($clothingQuery);
$clothingStatement->bindValue(':Swid', $playerSwid);
$clothingStatement->execute();
$playerClothing = $clothingStatement->fetch(PDO::FETCH_ASSOC);
$clothingStatement->closeCursor();

header('Content-type: image/png');

$colorResource = returnPaperResource($playerClothing['Color'], $avatarPaperSize);
unset($playerClothing['Color']);

if($playerClothing['Photo'] != 0){
	$imageResource = returnPaperResource($playerClothing['Photo'], $avatarPaperSize);
	if($imageResource === false) {
		$imageResource = $colorResource;
	} else {
		imagecopyresampled($imageResource, $colorResource, 0, 0, 0, 0, imagesx($imageResource), imagesy($imageResource), imagesx($colorResource), imagesy($colorResource));
	}
} else {
	$imageResource = $colorResource;
}

unset($playerClothing['Photo']);

foreach($playerClothing as $clothingPart => $itemId){
	if($itemId != 0){
		$clothingResource = returnPaperResource($itemId, $avatarPaperSize);
		if($clothingResource !== false) {
			imagecopyresampled($imageResource, $clothingResource, 0, 0, 0, 0, imagesx($imageResource), imagesy($imageResource), imagesx($clothingResource), imagesy($clothingResource));
		}
	}
}

imagealphablending($imageResource, false);
imagesavealpha($imageResource, true);

imagepng($imageResource);
imagedestroy($imageResource);

unset($database);

?>