<?php
header('Access-Control-Allow-Origin: *');

if ( ! empty( $_POST['ingredients'] ) ) {
	header('Content-Type: application/json');
	$ingredients = parse_ingredients( $_POST['ingredients'] );
	$response = $ingredients;
	if ( $_GET['action'] == 'shopping_list' ) {
		$response = ingredients_to_shopping_list($ingredients);
	}
	echo json_encode($response);
} else {
?>
<title>AARSE Ingredients Parsing API</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<style>
	body {
		background: tomato;
		margin: 0;
		padding: 0;
	}
	h1 {
		color: lemonchiffon;
		font-size: 5em;
		text-align: center;
		margin: .5em auto;
	}

	.row {
		display: flex;
		flex-wrap: wrap; /* 2 */
	}
	.col {
		flex: 1 0 5em;
		margin: 0.5em;
	}
	textarea, pre {
		width: 100%;
		background: lemonchiffon;
		color: black;
		font-family: monospace;
		min-height: 200px;
		vertical-align: top;
		margin: 0;
		border: 0;
	}
	button {
		background: brown;
		border: none;
		padding: 1em 2em;
		margin: 1em 0;
		float: right;
		color: lemonchiffon;
		font-weight: bold;
		font-size: 1.5em;
	}
	label {
		color: lemonchiffon;
		padding: 1em 0;
		font-size: 1.25em;
		font-weight: bold;
	}
</style>
<h1>AARSE Ingredients Parsing API</h1>

<div class="row">
	<div class="col">
		<label for="ingredients">Paste your ingredients here</label>
		<textarea id="ingredients" cols="30" rows="10">
1 tablespoon finely ground coffee
2 teaspoons chili powder
1 teaspoon onion powder
1 teaspoon coarsely ground pepper
1 teaspoon ground mustard
1/2 teaspoon salt
1/2 teaspoon garlic powder
1/2 teaspoon dried oregano
1/4 teaspoon cayenne pepper
1 beef ribeye roast (4 to 5 pounds)
2 tablespoons olive oil
		</textarea>
		<button id="parse">Try it!</button>
	</div>
	<div class="col">
		<label for="results">See awesome JSON here</label>
		<pre id="results"></pre>
	</div>
</div>
<script>
	(function($){
		$(function(){
			$('#parse').on('click',function(){
				$.post('/',{ ingredients:$('#ingredients').val() }).then(function(json_response){
					$('#results').text(JSON.stringify(json_response, null, 4));
				});
			});
		})
	})(jQuery);
</script>
<?php
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
		}
		if ( is_numeric( $ingredient['float_qty'] ) ) {
			$grouped_ingredients[$ingredient['name'] . '::' . $ingredient['unit']]['amount'] += (float) $ingredient['float_qty'];
		}
	}
	foreach( $grouped_ingredients as &$ingredient ) {
		$ingredient['amount'] = float_to_fraction($ingredient['amount']);
	}
	return array_values( $grouped_ingredients );
}