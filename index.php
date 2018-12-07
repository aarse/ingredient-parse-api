<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

if ( ! empty( $_POST['ingredients'] ) ) {
	$temp = tmpfile();
	fwrite($temp, $_POST['ingredients']);
	$path = stream_get_meta_data($temp)['uri'];
	$command = 'docker run -v ' . $path . ':/app/input.txt ingredients-parser bin/parse-ingredients-as-json.py input.txt';
	echo(shell_exec($command));
	fclose($temp);
} else {
	echo "{}";
}