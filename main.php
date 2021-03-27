<?php
DEFINE ("DATASET_EMPTY",				-1);
DEFINE ("DATASET_SIZE_DIFFERS",			-2);
DEFINE ("DATASET_MISSING DIMENSION",	-3);

// Aux

function powerTwo($number) {
	$squared	= pow(floatval($number), 2);	

	return $squared;
}

// Partials

function getPartials($dataset) {
	$partials	= Array();

	if (!$dataset)										return DATASET_EMPTY;
	if (!$dataset["x"] || !$dataset["y"])				return DATASET_MISSING_DIMENSION;
	if (sizeof($dataset["x"]) != sizeof($dataset["y"]))	return DATASET_SIZE_DIFFERS;

	$dataSize		= sizeof($dataset["x"]);
	$x_vals			= array_map("floatval", $dataset["x"]);
	$y_vals			= array_map("floatval", $dataset["y"]);
	$x_sqr_vals		= array_map("powerTwo", $x_vals);

	$x_dot_y_vals	= Array();	
	for ($i = 0; $i < $dataSize; $i++) {
		$x_dot_y_vals[$i]	= $x_vals[$i] * $y_vals[$i];	
	}

	$partials["x_vals"]			= $x_vals;
	$partials["y_vals"]			= $y_vals;
	$partials["x_sqr_vals"]		= $x_sqr_vals;
	$partials["x_dot_y_vals"]	= $x_dot_y_vals;
	
	return $partials;		
}

// Linearize

function linearize($partials) {
	$linearized		= Array();

	$x_vals_sum		= array_sum($partials["x_vals"]);
	$y_vals_sum		= array_sum($partials["y_vals"]);
	$x_sqr_vals_sum	= array_sum($partials["x_sqr_vals"]);
	$x_dot_y_sum	= array_sum($partials["x_dot_y_vals"]);
	$dataset_size	= sizeof($partials["x_vals"]);

	$m_xy			= (floatval($x_dot_y_sum - (($x_vals_sum * $y_vals_sum)/$dataset_size)));
	$m_xx			= (floatval($x_sqr_vals_sum - (powerTwo($x_vals_sum)/$dataset_size)));

	$linear_c		= (floatval($m_xy/$m_xx));
	$angular_c		= (floatval(($y_vals_sum - ($linear_c * $x_vals_sum))/$dataset_size));


	$linearized["m_xy"]			= $m_xy;
	$linearized["m_xx"]			= $m_xx;
	$linearized["linear_c"]		= $linear_c;
	$linearized["angular_c"]	= $angular_c;

	return $linearized;
}

// Std deviation

function getStdDeviation($partials, $linearParameters) {
	$stdDeviationArr		= Array();

	$linear_c			= $linearParameters["linear_c"];
	$angular_c			= $linearParameters["angular_c"];
	$x_vals				= $partials["x_vals"];
	$y_vals				= $partials["y_vals"];
	$dataset_size		= count($x_vals);

	$std_dev_partials	= Array();

	for($i = 0; $i < $dataset_size; $i++) {
		$value				= powerTwo($y_vals[$i] - ($linear_c * $x_vals[$i] + $angular_c));

		$std_dev_partials[]	= $value;
	}

	$std_dev_partials_sum		= array_sum($std_dev_partials);
	$std_dev					= floatval($std_dev_partials_sum/($dataset_size - 2.0));

	$stdDeviationArr["std_dev"]					= $std_dev;
	$stdDeviationArr["std_dev_partials_sum"]	= $std_dev_partials_sum;
	$stdDeviationArr["std_dev_partials"]		= $std_dev_partials;
	
	return $stdDeviationArr;
}

// Errors 

function getErrors($partials, $linearParams, $stdDeviation) {
	$errors				= Array();
	$std_deviation		= $stdDeviation["std_dev"];
	$m_xx				= $linearParams["m_xx"];
	$x_sqr_vals_sum		= array_sum($partials["x_sqr_vals"]);
	$x_vals				= $partials["x_vals"];
	$dataset_size		= count($x_vals);

	$error_linear		= sqrt(floatval($std_deviation/$m_xx));
	$error_angular		= floatval($error_linear * sqrt($x_sqr_vals_sum/$dataset_size));

	$errors["linear"]	= $error_linear;
	$errors["angular"]	= $error_angular;

	return $errors;
}

// Log

function applyLog10($dataset) {
	$newDataset	= Array();	

	foreach ($dataset as $key => $values) 
		$newDataset[$key]		= array_map("log10", $values);

	return $newDataset;
}

// Cos

function cosSqr($value) {
    $radVal = deg2rad($value);
    $cos    = cos($radVal);

    $res    = powerTwo($cos);

    return $res;
}

function applyCosSqrToX($dataset) {
	$newDataset	= Array();	

	foreach ($dataset as $key => $values) {
		if ($key == "x") 
		    $newDataset[$key]		= array_map("cosSqr", $values);
		else
		    $newDataset["y"]		= $values;
	}

	return $newDataset;
}


// Main

$reports	= json_decode(file_get_contents("data.json"), TRUE);

foreach ($reports as $reportName => $raw_dataset) {

	echo "Processing report $reportName\n";

	$dataset	= $raw_dataset;

	if ($argv[1] == "--log10"){
		$dataset	= applyLog10($raw_dataset);
	}
	else if ($argv[1] == "--cos**2") {
		$dataset	= applyCosSqrToX($raw_dataset);
	}

	$partials		= getPartials($dataset);
	if (!$partials) {
		switch ($partials) {
		case DATASET_EMPTY:
			echo "Dataset is empty!\n";
			break;
		case DATASET_MISSING_DIMENSION:
			echo "One dimension is missing!\n";
			break;
		case DATASET_SIZE_DIFFERS:
			echo "Dimensions differ in size!\n";
			break;
		}

		exit;
	}

	$linearParams	= linearize($partials);
	$stdDeviation	= getStdDeviation($partials, $linearParams);
	$errors			= getErrors($partials, $linearParams, $stdDeviation); 
	$dataset_size	= count($partials["x_vals"]);

	echo "Xi\t\t\tYi\t\t\tXi**2\t\t\tXi*Yi\t\t\t(Yi - (m'*Xi + b')**2)\n";
	for ($i = 0; $i < $dataset_size; $i++) {
		$index	= $i+1;
	
		$x			= number_format($partials["x_vals"][$i], 4);
		$y			= number_format($partials["y_vals"][$i], 4);
		$x_sqr		= number_format($partials["x_sqr_vals"][$i], 4);
		$x_dot_y	= number_format($partials["x_dot_y_vals"][$i], 4);
		$std_part	= number_format($stdDeviation["std_dev_partials"][$i], 4);

		echo "$x\t\t\t";
		echo "$y\t\t\t";
		echo "$x_sqr\t\t\t";
		echo "$x_dot_y\t\t\t";
		echo "$std_part\t\t\t\n";
	}

	echo "\nMxx = " . number_format($linearParams["m_xx"], 4);
	echo "\t\tMxy = " . number_format($linearParams["m_xy"], 4) . "\n";

	echo "m' = " . number_format($linearParams["linear_c"], 4);
	echo "\t\tb' = ". number_format($linearParams["angular_c"], 4) . "\n";

	echo "em = " . number_format($errors["linear"], 4);
	echo "\t\teb = " . number_format($errors["angular"], 4);
	
	echo "\t\tsigma**2 = " . number_format($stdDeviation["std_dev"], 4) . "\n";
	
}
?>
