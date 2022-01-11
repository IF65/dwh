<?php

/** registro il loader */
spl_autoload_register(
	function ($class) {
		$folders = explode("\\", $class);

		$location = implode('/',$folders);
		if (preg_match('/^If65\/(.*)$/', $location, $matches)) {
			$location = $matches[1];
		}

		require(realpath(__DIR__) . '/' . $location . '.php');
	}
);

