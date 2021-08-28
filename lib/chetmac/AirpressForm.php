<?php

$schema = getSchema("https://airtable.com/shrzYvxjThgcjR4Dh");

foreach($schema["formTable"]["columns"] as $field){

	renderField($field,$schema);

}

function renderField($field, $schema, $return=false){

	$renderFunction = "render_" . $field["type"];
	if ( function_exists($renderFunction) ){
		$output = $renderFunction($field, $schema);
	} else {
		$output = "<p>No render method exists for " . $field["type"] . "</p>";
	}

	// apply wordpress filter

	if ( $return ){
		return $output;
	} else {
		echo $output;
	}

}

function render_text($field, $schema){
	ob_start();
	?>
	<div class="form-group">
		<label><?php echo htmlspecialchars($field['name']);?></label>
		<input type="text" name="<?php echo htmlspecialchars($field['name']);?>" id="<?php echo $field['name'];?>">
	</div>
	<?php
	return ob_get_clean();
}

function render_number($field, $schema){

	// format: "currency"
	// negative: false
	// precision: 2
	// symbol: "$"
	// validatorName: "positive"
	
	ob_start();
	?>
	<div class="form-group">
		<label><?php echo htmlspecialchars($field['name']);?></label>
		<input type="number" name="<?php echo htmlspecialchars($field['name']);?>" id="<?php echo $field['name'];?>">
	</div>
	<?php
	return ob_get_clean();
}

function render_select($field, $schema){

	$meta = $schema["formTable"]["views"][0]["metadata"]["form"]["fieldsByColumnId"][$field["id"]];

	$list = false;
	
	// do option
	$options = [];
	foreach( $field["typeOptions"]["choiceOrder"] as $sel ){

		if ( isset($meta["whitelistedChoiceIds"]) && ! in_array($sel, $meta["whitelistedChoiceIds"]) ){
			continue; // There is a whitelist and this item is not in it
		}

		$options[$sel] = $field["typeOptions"]["choices"][$sel];

	}

	if ( $meta ){

		$list = isset($meta["shouldShowAsList"]) ? $meta["shouldShowAsList"] : false;

	}

	ob_start();

	if ( $list ):
	?>
	<div class="form-group">
		<label><?php echo htmlspecialchars($field['name']);?></label>
		<?php foreach($options as $o): ?>
			<label><input type="radio" name="<?php echo htmlspecialchars($field["name"]); ?>" id="<?php echo $o["id"]; ?>"> <?php echo htmlspecialchars($o["name"]); ?></label>
		<?php endforeach; ?>
	</div>
	<?php
	else:
	?>
	<div class="form-group">
		<label for="<?php echo $field['id'];?>"><?php echo htmlspecialchars($field['name']);?></label>
		<select name="<?php echo htmlspecialchars($field['name']);?>" id="<?php echo $field['name'];?>">
			<option>Select an option</option>
			<?php foreach($options as $o): ?>
				<option value="<?php echo htmlspecialchars($o["name"]); ?>" id="<?php echo $field["id"]; ?>"><?php echo htmlspecialchars($o["name"]); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php
	endif;

 	return ob_get_clean();
}

function getSchema($url){

	if ( ! preg_match("/(shr[a-zA-Z0-9]{14})/", $url, $matches) ){
		return false;
	}

	$form_id = $matches[1];

	// Handle Caching
	if ( file_exists($form_id.".json") ){
		return json_decode(file_get_contents($form_id . ".json"), true)["data"];
	}

	// First get the Raw HTML of the public form
	$raw = file_get_contents("https://airtable.com/" . $matches[1]);

	// Extract and decode the relevant information
	$json = easyParse($raw, "window.initData =","</script>", false, false);

	$json = trim(trim($json),";");

	$data = json_decode($json,true);

	// Build the request to get the ACTUAL schema data
	$base_url = "https://airtable.com/v0.3/view/";
	$view_id = $data["sharedViewId"];
	$access_policy = json_decode($data["accessPolicy"],true);

	$params = [
		"accessPolicy" => $data["accessPolicy"]
	];

	$param_string = "/readSharedFormData?" . http_build_query($params);

	$url = $base_url . $view_id . $param_string;

	// Make the request
	$curl = curl_init();

	curl_setopt_array($curl, array(
	  CURLOPT_URL => $url,
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => "",
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 30,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "GET",
	  CURLOPT_HTTPHEADER => array(
	    "Accept: */*",
	    "Cache-Control: no-cache",
	    "Connection: keep-alive",
	    "Host: airtable.com",
	    "X-Requested-With: XMLHttpRequest",
	    "accept-encoding: gzip, deflate",
	    "cache-control: no-cache",
	    "x-airtable-application-id: " . $access_policy["applicationId"],
	    "x-time-zone: America/Los_Angeles",
	    "x-user-locale: en"
	  ),
	));

	$response = curl_exec($curl);
	$err = curl_error($curl);

	curl_close($curl);

	if ($err) {
	  die("cURL Error #:" . $err);
	}

	file_put_contents($form_id . ".json", $response);

	return json_decode($response,true)["data"];

}

function easyParse($string, $start, $end, $include_start=true, $include_end=false){
	$s = strpos($string, $start);

	if ( ! $include_start ){
		$s += strlen($start);
	}

	$e = strpos($string, $end, $s);

	if ( $include_end ){
		$e += strlen($end);
	}

	return substr($string, $s, $e-$s);
}

?>