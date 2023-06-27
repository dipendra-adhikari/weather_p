<?php

// Read the existing dataset file
$datasetFile = 'ktm.csv';
$existingData = array_map('str_getcsv', file($datasetFile));

// Remove the header row from the existing data
$header = array_shift($existingData);

// Include the LinearRegression class

class LinearRegression
{
    private $slope;
    private $intercept;

    public function train($X, $y)
    {
        $n = count($X);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumX += $X[$i][0];
            $sumY += $y[$i];
            $sumXY += $X[$i][0] * $y[$i];
            $sumXX += $X[$i][0] * $X[$i][0];
        }

        $meanX = $sumX / $n;
        $meanY = $sumY / $n;

        $this->slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $this->intercept = $meanY - $this->slope * $meanX;
    }

    public function predict($X)
    {
        return $this->slope * $X[0][0] + $this->intercept;
    }
}

// Prepare the training data
$X = array(); // Input features (temperature 2 days before)
$Y_min = array(); // Output labels (minimum temperature today)
$Y_max = array(); // Output labels (maximum temperature today)

// Loop through the data starting from the third row
for ($i = 2; $i < count($existingData); $i++) {
    $tempMinToday = floatval($existingData[$i][3]); // Minimum temperature today
    $tempMaxToday = floatval($existingData[$i][2]); // Maximum temperature today
    $tempTwoDaysBefore = floatval($existingData[$i - 2][3]); // Temperature two days before

    $X[] = array($tempTwoDaysBefore);
    $Y_min[] = $tempMinToday;
    $Y_max[] = $tempMaxToday;
}

// Train the linear regression models
$regression_min = new LinearRegression();
$regression_min->train($X, $Y_min);

$regression_max = new LinearRegression();
$regression_max->train($X, $Y_max);

// Number of days to predict
$daysToPredict = 5;

// Get the last 400 days' data for prediction
$historyData = array_slice($existingData, -400);

// Prepare the data for prediction
$X_pred = array();
$Y_pred_min = array();
$Y_pred_max = array();

// Loop through the history data for prediction
for ($i = 2; $i < count($historyData); $i++) {
    $tempTwoDaysBefore = floatval($historyData[$i - 2][3]); // Temperature two days before

    $X_pred[] = array($tempTwoDaysBefore);
    $Y_pred_min[] = floatval($historyData[$i][3]); // Minimum temperature today
    $Y_pred_max[] = floatval($historyData[$i][2]); // Maximum temperature today
}

// Predict the temperatures for the next 5 days
$predicted_temperatures = array();

for ($i = 0; $i < $daysToPredict; $i++) {
    // Get the temperature two days before the predicted day
    $tempTwoDaysBefore = floatval($X_pred[count($X_pred) - 5 + $i][0]);

    // Predict the minimum temperature for the next day using the temperature two days before
    $next_day_min = $regression_min->predict(array(array($tempTwoDaysBefore)));

    // Predict the maximum temperature for the next day using the temperature two days before
    $next_day_max = $regression_max->predict(array(array($tempTwoDaysBefore)));

    $predicted_temperatures[] = array($next_day_min, $next_day_max);
}

// Prepare the data for writing to CSV
$final_data = array();

// Get the current date
$current_date = date('Y-m-d');

// Loop through the predicted temperatures and add them to the final data
for ($i = 0; $i < $daysToPredict; $i++) {
    // Get the next date
    if ($i === 0) {
        // For the first day, use the current date
        $next_date = date('m-d-Y', strtotime($current_date));
    } else {
        // For the subsequent days, use the real-time dates
        $next_date = date('m-d-Y', strtotime($current_date . ' + ' . $i . ' days'));
    }

    // Check if the date already exists in the existing data
    $dateExists = false;
    foreach ($existingData as $row) {
        if ($row[1] === $next_date) {
            $dateExists = true;
            break;
        }
    }

    // Add the data to the final data array if the date doesn't already exist
    if (!$dateExists) {
        $final_data[] = array('Kathmandu', $next_date, number_format($predicted_temperatures[$i][1], 2), number_format($predicted_temperatures[$i][0], 2));
    }
}

// Append the new data to the existing data
$merged_data = array_merge($existingData, $final_data);

// Write the merged data to CSV
$fp = fopen('ktm.csv', 'w');

// Write the header row
fputcsv($fp, $header);

// Write the merged data rows
foreach ($merged_data as $row) {
    fputcsv($fp, $row);
}

fclose($fp);

echo 'Data stored successfully in ktm.csv file.<br>';

// Print today's date
$today = date('F d, Y', strtotime($current_date));
echo "Day 1 ($today): Predicted Minimum Temperature: " . number_format($predicted_temperatures[0][0], 2) . "°C, Predicted Maximum Temperature: " . number_format($predicted_temperatures[0][1], 2) . "°C<br>";

// Print the predicted temperatures for the next 4 days
for ($i = 1; $i < $daysToPredict; $i++) {
    $day = $i + 1;
    $next_date = date('F d, Y', strtotime($current_date . ' + ' . $i . ' days'));
    echo "Day $day ($next_date): Predicted Minimum Temperature: " . number_format($predicted_temperatures[$i][0], 2) . "°C, Predicted Maximum Temperature: " . number_format($predicted_temperatures[$i][1], 2) . "°C<br>";
}

?>
