<?php
//include ("ajustment.php");
//include ("errors.php");
//include ("std_deviation.php");

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

function getStdDeviation($partials) {
}


// Main

$reports	= json_decode(file_get_contents("data.json"), TRUE);

foreach ($reports as $reportName => $dataset) {

	echo "Processing report $reportName\n";

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
	$stdDeviation	= getStdDeviation($partials);
	$errors			= getErrors($partials, $linearParams, $stdDeviation); 

	writeReport($linearParams, $stdDeviation, $stdDeviation, NULL);	
}
?>
