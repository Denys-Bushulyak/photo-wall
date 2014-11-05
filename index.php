<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

$app = new Silex\Application();

//$app['debug'] = true;

$app->register(new Silex\Provider\MonologServiceProvider(), array(
	'monolog.logfile' => __DIR__ . '/log/development.log',
));

$app->register(new Silex\Provider\TwigServiceProvider(), array(
	'twig.path' => __DIR__ . '/views',
));

$app->get('/', function () use ($app) {
	$images = array();
	if ($handle = opendir(__DIR__ . '/images/')) {
		while (false !== ($entry = readdir($handle))) {
			$images[] = $entry;
		}
		closedir($handle);
	}
	return $app['twig']->render('master_page.twig', $images);
});

$app->post('/upload', function (Request $request) use ($app) {

	$new_height = 200;
	$padding = 10; //Отступ от края

	$response = new Response();

	try {

		/** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
		$file = $request->files->get('payload');

		$rightFileType = preg_match('/^text\/plain$/', $file->getMimeType());

		if ($rightFileType) {

			$handle = fopen($file->getRealPath(), 'r');

			if ($handle) {

				$watermark = imagecreatefrompng(__DIR__ . '/watermark.png');
				$watermark_width = imagesx($watermark);
				$watermark_height = imagesY($watermark);

				$errors = $images = array(); //Неуспешные загрузки

				while (false !== ($pathToImage = fgets($handle))) {

					#region Scale
					$pathToImage = trim($pathToImage,"\r\n");

					$filename = pathinfo($pathToImage, PATHINFO_BASENAME);
					$filename = urldecode($filename);

					$destination = __DIR__.'/images/'. $filename;

					if(file_exists($destination)){
						$errors[] = "File already exists: " . $filename;
						$images[] = $filename;
						continue;
					}

					$file = file_get_contents($pathToImage);

					if(!$file){
						$errors[] = "Can't load file: " . $pathToImage;
						continue;
					}

					$image = imagecreatefromstring($file);

					$image_width = imagesx($image);
					$image_height = imagesy($image);

					$new_width = $image_width / $image_height * $new_height;
					$scaled_image = imagecreatetruecolor($new_width, $new_height);
					imagecopyresampled($scaled_image, $image, 0, 0, 0, 0, $new_width, $new_height, $image_width, $image_height);
					#endregion


					imagecopy($scaled_image, $watermark, $new_width - $watermark_width - $padding, $new_height - $watermark_height - $padding, 0, 0, $watermark_width, $watermark_height);



					$images[] = $filename;

					switch (pathinfo($pathToImage, PATHINFO_EXTENSION)) {
						case 'png':
							imagepng($scaled_image, $destination);
							break;
						case 'jpeg':
						case 'jpg':
							imagejpeg($scaled_image, $destination);
							break;
					}


				}

				$response->setStatusCode(201);
				$response->setContent(json_encode(array('files' => $images, 'errors' => $errors)));

			} else {
				throw new \Symfony\Component\Filesystem\Exception\IOException;
			}


		} else {
			throw new \Symfony\Component\Filesystem\Exception\IOException("Wrong file format.");
		}

	} catch (IOExceptionInterface $e) {

		$response->setStatusCode(500);
		$response->setContent("An error occurred while creating your directory at " . $e->getPath());
	}

	return $response;
});


$app->run();
