<?php
namespace DynamicPriceOptimizer\Core;

/**
 * Handles machine learning functionality
 */
class MLEngine {
    /**
     * Python executable path
     *
     * @var string
     */
    private $python_path;

    /**
     * ML scripts directory
     *
     * @var string
     */
    private $scripts_dir;

    /**
     * Constructor
     */
    public function __construct() {
        $this->python_path = $this->find_python_path();
        $this->scripts_dir = DPO_PLUGIN_DIR . 'ml/python';
    }

    /**
     * Train ML model
     *
     * @return bool
     */
    public function train_model() {
        try {
            // Prepare data directory
            $upload_dir = wp_upload_dir();
            $data_dir = $upload_dir['basedir'] . '/dpo-data';
            if (!file_exists($data_dir)) {
                wp_mkdir_p($data_dir);
            }

            // Collect training data
            $this->collect_training_data($data_dir);

            // Train model
            $output = array();
            $return_var = 0;
            $command = sprintf(
                '%s %s/train_model.py --data_dir %s',
                escapeshellcmd($this->python_path),
                escapeshellarg($this->scripts_dir),
                escapeshellarg($data_dir)
            );

            exec($command, $output, $return_var);

            if ($return_var !== 0) {
                throw new \Exception('Model training failed: ' . implode("\n", $output));
            }

            return true;
        } catch (\Exception $e) {
            $this->log_error($e->getMessage());
            return false;
        }
    }

    /**
     * Predict price using ML model
     *
     * @param array $data
     * @return float
     */
    public function predict_price($data) {
        try {
            // Prepare input data
            $input_file = tempnam(sys_get_temp_dir(), 'dpo_');
            file_put_contents($input_file, json_encode($data));

            // Get model path
            $upload_dir = wp_upload_dir();
            $model_dir = $upload_dir['basedir'] . '/dpo-models';
            $model_path = $model_dir . '/latest_model.joblib';

            if (!file_exists($model_path)) {
                throw new \Exception('Model file not found');
            }

            // Make prediction
            $output = array();
            $return_var = 0;
            $command = sprintf(
                '%s %s/predict_price.py --model_path %s --input_file %s',
                escapeshellcmd($this->python_path),
                escapeshellarg($this->scripts_dir),
                escapeshellarg($model_path),
                escapeshellarg($input_file)
            );

            exec($command, $output, $return_var);

            // Clean up
            unlink($input_file);

            if ($return_var !== 0) {
                throw new \Exception('Price prediction failed: ' . implode("\n", $output));
            }

            // Parse output
            $result = json_decode($output[0], true);
            if (!isset($result['price'])) {
                throw new \Exception('Invalid prediction output');
            }

            return floatval($result['price']);
        } catch (\Exception $e) {
            $this->log_error($e->getMessage());
            return 0;
        }
    }

    /**
     * Collect training data
     *
     * @param string $data_dir
     */
    private function collect_training_data($data_dir) {
        try {
            $output = array();
            $return_var = 0;
            $command = sprintf(
                '%s %s/collect_data.py --output_dir %s',
                escapeshellcmd($this->python_path),
                escapeshellarg($this->scripts_dir),
                escapeshellarg($data_dir)
            );

            exec($command, $output, $return_var);

            if ($return_var !== 0) {
                throw new \Exception('Data collection failed: ' . implode("\n", $output));
            }
        } catch (\Exception $e) {
            $this->log_error($e->getMessage());
        }
    }

    /**
     * Find Python executable path
     *
     * @return string
     */
    private function find_python_path() {
        $python_paths = array('python3', 'python');
        
        foreach ($python_paths as $path) {
            $output = array();
            $return_var = 0;
            
            exec(sprintf('%s -V', escapeshellcmd($path)), $output, $return_var);
            
            if ($return_var === 0) {
                return $path;
            }
        }
        
        throw new \Exception('Python executable not found');
    }

    /**
     * Log error
     *
     * @param string $message
     */
    private function log_error($message) {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/dpo-logs/ml-engine.log';
        
        $log_entry = sprintf(
            "[%s] %s\n",
            current_time('mysql'),
            $message
        );
        
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
} 