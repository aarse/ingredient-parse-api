<?php
header('Access-Control-Allow-Origin: *');
//header('Content-Type: application/json');

if ( ! empty( $_POST['ingredients'] ) ) {
	$ingredients = parse_ingredients( $_POST['ingredients'] );
	$response = $ingredients;
	if ( $_GET['action'] == 'shopping_list' ) {
		$response = ingredients_to_shopping_list($ingredients);
	}
	//var_dump($ingredients,$response);
	echo json_encode($response);
} else {
	echo "{}";
}

function parse_ingredients($ingredients_str) {
	$temp = tmpfile();
	fwrite($temp, $ingredients_str);
	$path = stream_get_meta_data($temp)['uri'];
	$command = 'docker run -v ' . $path . ':/app/input.txt ingredients-parser bin/parse-ingredients-as-json.py input.txt';
	$results = json_decode(shell_exec($command), true);
	foreach( $results as &$result ) {
		if ( empty( $result['name'] ) && ! empty( $result['other'] ) ) {
			$result['name'] = $result['other'];
			$result['other'] = '';
		}

		// Parse edge case for branded ingredients
		if ( empty( $result['qty'] ) && ! empty( $result['other'] ) && preg_match('/[0-9 \/]+/m', $result['other'] ) == 1 ) {
			$result['qty'] = $result['other'];
			$result['other'] = '';
		}

		// Try to return a numeric value for fractions (e.g. 1 1/4 becomes 1.25)
		$result['float_qty'] = $result['qty'];
		if ( ! is_numeric( $result['float_qty'] ) ) {
			$result['float_qty'] = fraction_to_float( $result['float_qty'] );
		}
	}
	fclose($temp);
	return $results;
}

function fraction_to_float($fraction) {
    $int = 0;
    $float = 0;
    $parts = explode(' ', $fraction);
    if (count($parts) >= 1) {
        $int = $parts[0];
    }
	$float_str = $parts[0];
    if (count($parts) >= 2) {
        $float_str = $parts[1];
    }
	list($top, $bottom) = explode('/', $float_str);
	$float = $top / $bottom;

    return $int + $float;
}

function float_to_fraction($n, $tolerance = 1.e-6) {
    $h1=1; $h2=0;
    $k1=0; $k2=1;
    $b = 1/$n;
    do {
        $b = 1/$b;
        $a = floor($b);
        $aux = $h1; $h1 = $a*$h1+$h2; $h2 = $aux;
        $aux = $k1; $k1 = $a*$k1+$k2; $k2 = $aux;
        $b = $b-$a;
    } while (abs($n-$h1/$k1) > $n*$tolerance);


    // Integer
    if ( $k1 == 1 ) {
    	return $h1;
    }

    // Integer & fraction
    if ( $h1 > $k1 ) {
    	$j1 = floor($h1/$k1);
    	$h1 = $h1 % $k1;
    	return "$j1 $h1/$k1";
    }

    // Fraction only
    return "$h1/$k1";
}

function ingredients_to_shopping_list($ingredients) {
	$grouped_ingredients = [];
	foreach($ingredients as $ingredient) {
		if ( empty( $grouped_ingredients[$ingredient['name'] . '::' . $ingredient['unit']] ) ){
			$grouped_ingredients[$ingredient['name'] . '::' . $ingredient['unit']] = [
				'name' => $ingredient['name'],
				'unit' => $ingredient['unit'],
				'amount' => 0
			];
			if ( is_numeric( $ingredient['float_qty'] ) ) {
				$grouped_ingredients[$ingredient['name'] . '::' . $ingredient['unit']]['amount'] += (float) $ingredient['float_qty'];
			}
		}
	}
	foreach( $grouped_ingredients as &$ingredient ) {
		$ingredient['amount'] = float_to_fraction($ingredient['amount']);
	}
	return array_values( $grouped_ingredients );
}