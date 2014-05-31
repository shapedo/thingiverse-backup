<?php

/**
 * Website: http://shapedo.com
 * 
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author Amit Dar
 */
class ThingiverseBackup {
	
	/**
	 * @var string
	 */
	private $destinationFolder = '';
	
	/**
	 * @var boolean
	 */
	private $allowOverride = false;
	
	public function backup($username, $destinationFolder = '', $allowOverride = false) {
		if (empty($destinationFolder) || ! is_dir($destinationFolder) || ! is_writable($destinationFolder)) {
			echo 'Destination folder not exists or not writable';
			return;
		}
		$this->destinationFolder = $destinationFolder; 
		$this->allowOverride = $allowOverride;
		
		$thingies = $this->getUserThingies($username);
		
		if (is_null($thingies)) {
			echo 'Cannot fetch shapes';
			return;
		}
		
		foreach ($thingies as $thingId => $thingName) {
			$this->parseThing($thingId);
		}
	}
	
	/**
	 * retrieve thingies names and ids for given username
	 * @param string $username
	 * @return NULL|array
	 */
	public function getUserThingies($username) {
		require_once 'simple_html_dom.php';
			
		$html = @file_get_contents('http://www.thingiverse.com/' . $username . '/designs');
		if ($html === false) {
			return null;
		}
			
		$html = str_get_html($html);
		if ($html === false) {
			return null;
		}
			
		$userId = '';
		foreach($html->find('link[rel=alternate]') as $element) {
			$href = $element->getAttribute('href');
			$userId = substr($href, strpos($href, 'user:') + strlen('user:'));
		}
			
		$max = 100;
		$page = 1;
			
		$shapesIds = array();
		$shapesNames = array();
			
		$shapesContent = $this->getThingiverseUserShapes($userId, $page);
		while ($page < $max && ! empty($shapesContent)) {
			// parse shapes
			$html = str_get_html($shapesContent);
	
			foreach($html->find('a[class=thing-img-wrapper]') as $element) {
				$href = $element->getAttribute('href');
				$shapesIds[] = substr($href, strpos($href, 'thing:') + strlen('thing:'));
			}
	
			foreach($html->find('span[class=thing-name]') as $element) {
				$shapesNames[] = $element->innertext;
			}
	
			++$page;
			$shapesContent = $this->getThingiverseUserShapes($userId, $page);
		}
	
		$shapes = array();
		foreach ($shapesIds as $key => $id) {
			$shapes[$id] = $shapesNames[$key];
		}
	
		return $shapes;
	}
	
	private function parseThing($thingId) {
		$baseUrl = 'http://thingiverse.com';
	
		// parse html
		$html = file_get_html($baseUrl . '/thing:' . $thingId);
		if ($html === false) {
			return false;
		}
	
		// description
		$description = '';
		foreach($html->find('#description') as $element) {
			$description = $element->innertext;
		}
	
		// instructions
		$instructions = '';
		foreach($html->find('#instructions') as $element) {
			$instructions = $element->innertext;
		}
	
		// category
		$category = '';
		foreach($html->find('.thing-category') as $element) {
			$category = $element->href;
		}
	
		// tags
		$tags = array();
		foreach($html->find('.tags a') as $element) {
			$tags[] = $element->innertext;
		}
	
		// license
		$license = '';
		foreach($html->find('a[rel=license]') as $element) {
			$license = $element->innertext;
		}
	
		// title
		$title = '';
		foreach($html->find('.thing-header-data h1') as $element) {
			$title = $element->innertext;
		}
	
		// username and userlink
		$username = '';
		$userLink = '';
		foreach($html->find('.thing-header-data h2 a') as $element) {
			$username = $element->innertext;
			$userLink = $element->href;
		}
	
		// publish date
		$publishDate = '';
		foreach($html->find('.thing-header-data h2 time') as $element) {
			$publishDate = $element->getAttribute('datetime');
		}
	
		// images
		$images = array();
		foreach($html->find('.thing-page-image img') as $element) {
			$imageSrc = $element->getAttribute('data-img');
			if (! empty($imageSrc) && strpos($imageSrc, 'large') !== false) {
				$images[] = $imageSrc;
			}
		}
		
		$files = array();
		foreach($html->find('.thing-file a') as $element) {
			foreach ($element->find('.filename') as $filenameElement) {
				$files[$element->href] = $filenameElement->innertext;
			}
		}
		
		$name = preg_replace("/[^A-Za-z0-9]/", '', $title);
		$name = strtolower(str_replace(' ', '_', $name));
		
		// check if thing dir exists

		// download zip
		$thingDir = $this->destinationFolder . DIRECTORY_SEPARATOR . $name;
		
		// check if file exists and not allowed to override
		if (! $this->allowOverride && file_exists($thingDir)) {
			return;
		}
		
		// remove directory
		if (file_exists($thingDir)) {
			foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($thingDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
				chmod($path->getPathname(), 0755);
				$path->isFile() ? @unlink($path->getPathname()) : @rmdir($path->getPathname());
			}
			chmod($thingDir, 0755);
			@rmdir($thingDir);
		}
		
		if (! file_exists($thingDir)) {
			mkdir($thingDir, 0755, true);
		}
		if (! file_exists($thingDir . DIRECTORY_SEPARATOR . 'images')) {
			mkdir($thingDir . DIRECTORY_SEPARATOR . 'images', 0755, true);
		}
	
		$zipPath = $thingDir . DIRECTORY_SEPARATOR . $name . '.zip';
		if (file_exists($zipPath)) {
			unlink($zipPath);
		}
		 
		// download the zip
		$downloadDir = $thingDir . DIRECTORY_SEPARATOR . 'files';
		$uploads = array();
		if (! file_exists($downloadDir)) {
			mkdir($downloadDir, 0775, true);
		}
		foreach ($files as $file => $name) {
			$exploded = explode('.', $name);
			$fileExt = array_pop($exploded);
			
			$this->downloadFileFromUrl($baseUrl . $file, $downloadDir . DIRECTORY_SEPARATOR . $name);
			$uploads[] = $downloadDir . DIRECTORY_SEPARATOR . $name;
		}
		 
		// download images
		foreach ($images as $image) {
			$exploded = explode('/', $image);
			$imageFilename = array_pop($exploded);
	
			file_put_contents($thingDir . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $imageFilename, file_get_contents($image));
		}
		
		$params = array(
			'id'			=> $thingId,
			'url'			=> $baseUrl . '/thing:' . $thingId,
			'title' 		=> $title,
			'username' 		=> $username,
			'userLink' 		=> $baseUrl . $userLink,
			'license' 		=> $license,
			'category' 		=> str_replace('/categories/', '', $category),
			'description' 	=> trim($description),
			'instructions' 	=> trim($instructions),
			'tags' 			=> implode(',', $tags),
			'publishDate' 	=> $publishDate,
		);
		
		$fileContent = '';
		foreach ($params as $key => $value) {
			$fileContent .= "$key: $value\n";
		}
		
		file_put_contents($thingDir . DIRECTORY_SEPARATOR . 'data.txt', $fileContent);
	}
	
	private function downloadFileFromUrl ($url, $path) {
	
		$newfname = $path;
		$file = fopen ($url, "rb");
		if ($file) {
			$newf = fopen ($newfname, "wb");
	
			if ($newf)
			while(!feof($file)) {
				fwrite($newf, fread($file, 1024 * 8 ), 1024 * 8 );
			}
		}
	
		if ($file) {
			fclose($file);
		}
	
		if ($newf) {
			fclose($newf);
		}
	}
	
	private function getThingiverseUserShapes($userId, $page) {
		$postdata = http_build_query(
			array(
					'id' => $userId,
					'page' => $page,
			)
		);
	
		$opts = array('http' =>
			array(
				'method'  => 'POST',
				'header'  => 'Content-type: application/x-www-form-urlencoded',
				'content' => $postdata
			)
		);
	
		$context  = stream_context_create($opts);
	
		$result = @file_get_contents('http://www.thingiverse.com/ajax/user/designs', false, $context);
		if ($result === false) {
			return '';
		}
		 
		return $result;
	}
}