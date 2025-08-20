<?php

class MCPTools {
    private $otpStorage = [];
    private $vehicleData = [];
    
    public function __construct() {
        $this->initializeVehicleData();
    }
    
    private function initializeVehicleData() {
        $this->vehicleData = [
            'brands' => [
                'Maruti Suzuki', 'Hyundai', 'Tata', 'Mahindra', 'Honda', 
                'Toyota', 'Ford', 'Volkswagen', 'Nissan', 'Renault',
                'BMW', 'Mercedes', 'Audi', 'Skoda', 'Kia'
            ],
            'models' => [
                'Maruti Suzuki' => ['Swift', 'Baleno', 'Alto', 'WagonR', 'Vitara Brezza', 'Dzire', 'Ertiga'],
                'Hyundai' => ['i20', 'Creta', 'Verna', 'Grand i10', 'Venue', 'Elantra', 'Tucson'],
                'Tata' => ['Nexon', 'Harrier', 'Safari', 'Altroz', 'Tigor', 'Punch', 'Tiago'],
                'Honda' => ['City', 'Amaze', 'Jazz', 'WR-V', 'CR-V', 'Civic'],
                'Toyota' => ['Fortuner', 'Innova', 'Corolla', 'Yaris', 'Glanza', 'Urban Cruiser'],
                'BMW' => ['3 Series', '5 Series', 'X1', 'X3', 'X5', '7 Series'],
                'Mercedes' => ['C-Class', 'E-Class', 'S-Class', 'GLA', 'GLC', 'GLE'],
                'Audi' => ['A3', 'A4', 'A6', 'Q3', 'Q5', 'Q7']
            ]
        ];
    }
    
    public function sendOtp($params) {
        $mobileNumber = $params['mobile_number'] ?? '';
        
        if (empty($mobileNumber) || !preg_match('/^\d{10}$/', $mobileNumber)) {
            return [
                'success' => false,
                'error' => 'Invalid mobile number'
            ];
        }
        
        $otp = sprintf('%06d', rand(100000, 999999));
        $this->otpStorage[$mobileNumber] = [
            'otp' => $otp,
            'created_at' => time(),
            'attempts' => 0
        ];
        
        error_log("OTP for {$mobileNumber}: {$otp}");
        
        return [
            'success' => true,
            'message' => 'OTP sent successfully to ' . $mobileNumber,
            'otp_ref' => substr(md5($mobileNumber . $otp), 0, 8),
            'state_updates' => [
                'otp_sent' => true,
                'otp_sent_time' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    public function verifyOtp($params) {
        $mobileNumber = $params['mobile_number'] ?? '';
        $submittedOtp = $params['otp'] ?? '';
        
        if (empty($mobileNumber) || empty($submittedOtp)) {
            return [
                'success' => false,
                'error' => 'Mobile number and OTP are required'
            ];
        }
        
        if (!isset($this->otpStorage[$mobileNumber])) {
            return [
                'success' => false,
                'error' => 'No OTP found for this mobile number'
            ];
        }
        
        $otpData = $this->otpStorage[$mobileNumber];
        $otpData['attempts']++;
        
        if ($otpData['attempts'] > 3) {
            unset($this->otpStorage[$mobileNumber]);
            return [
                'success' => false,
                'error' => 'Maximum OTP attempts exceeded. Please request a new OTP.'
            ];
        }
        
        if (time() - $otpData['created_at'] > 300) {
            unset($this->otpStorage[$mobileNumber]);
            return [
                'success' => false,
                'error' => 'OTP expired. Please request a new OTP.'
            ];
        }
        
        if ($otpData['otp'] === $submittedOtp) {
            unset($this->otpStorage[$mobileNumber]);
            return [
                'success' => true,
                'message' => 'OTP verified successfully',
                'state_updates' => [
                    'otp_verified' => true,
                    'otp_verified_time' => date('Y-m-d H:i:s')
                ]
            ];
        }
        
        $this->otpStorage[$mobileNumber] = $otpData;
        return [
            'success' => false,
            'error' => 'Invalid OTP. Attempts remaining: ' . (3 - $otpData['attempts'])
        ];
    }
    
    public function requestPan($params) {
        $sessionId = $params['session_id'] ?? '';
        
        if (empty($sessionId)) {
            return [
                'success' => false,
                'error' => 'Session ID is required'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Please upload your PAN card document',
            'upload_url' => '/upload/pan/' . $sessionId,
            'accepted_formats' => ['jpg', 'jpeg', 'png', 'pdf'],
            'max_size' => '5MB',
            'state_updates' => [
                'pan_requested' => true,
                'pan_request_time' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    public function searchBrands($params) {
        $query = strtolower($params['query'] ?? '');
        $brands = $this->vehicleData['brands'];
        
        if (!empty($query)) {
            $brands = array_filter($brands, function($brand) use ($query) {
                return strpos(strtolower($brand), $query) !== false;
            });
        }
        
        return [
            'success' => true,
            'brands' => array_values($brands),
            'total' => count($brands),
            'message' => 'Vehicle brands retrieved successfully'
        ];
    }
    
    public function searchModels($params) {
        $make = $params['make'] ?? '';
        $query = strtolower($params['query'] ?? '');
        
        if (empty($make)) {
            return [
                'success' => false,
                'error' => 'Vehicle make/brand is required'
            ];
        }
        
        $models = $this->vehicleData['models'][$make] ?? [];
        
        if (!empty($query)) {
            $models = array_filter($models, function($model) use ($query) {
                return strpos(strtolower($model), $query) !== false;
            });
        }
        
        return [
            'success' => true,
            'make' => $make,
            'models' => array_values($models),
            'total' => count($models),
            'message' => 'Vehicle models retrieved successfully'
        ];
    }
    
    public function saveUser($params) {
        $requiredFields = ['name', 'email', 'mobile_number', 'income'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (empty($params[$field])) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            return [
                'success' => false,
                'error' => 'Missing required fields: ' . implode(', ', $missingFields),
                'missing_fields' => $missingFields
            ];
        }
        
        if (!filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error' => 'Invalid email format'
            ];
        }
        
        if (!preg_match('/^\d{10}$/', $params['mobile_number'])) {
            return [
                'success' => false,
                'error' => 'Invalid mobile number format'
            ];
        }
        
        $userData = [
            'name' => $params['name'],
            'email' => $params['email'],
            'mobile_number' => $params['mobile_number'],
            'income' => $params['income'],
            'employment_type' => $params['employment_type'] ?? '',
            'company_name' => $params['company_name'] ?? '',
            'address' => $params['address'] ?? '',
            'pincode' => $params['pincode'] ?? '',
            'saved_at' => date('Y-m-d H:i:s')
        ];
        
        return [
            'success' => true,
            'message' => 'User information saved successfully',
            'user_id' => md5($params['mobile_number'] . time()),
            'state_updates' => [
                'user_info_saved' => true,
                'user_info_saved_time' => date('Y-m-d H:i:s'),
                'user_info' => $userData
            ]
        ];
    }
    
    public function fetchOffers($params) {
        $vehicleCondition = $params['condition'] ?? [];
        $userInfo = $params['user_info'] ?? [];
        
        if (empty($vehicleCondition['make']) || empty($vehicleCondition['model'])) {
            return [
                'success' => false,
                'error' => 'Vehicle make and model are required for loan offers'
            ];
        }
        
        if (empty($userInfo['income'])) {
            return [
                'success' => false,
                'error' => 'User income information is required for loan offers'
            ];
        }
        
        $income = (float)$userInfo['income'];
        $vehicleValue = $this->estimateVehicleValue($vehicleCondition['make'], $vehicleCondition['model']);
        
        $offers = $this->generateOffers($income, $vehicleValue, $vehicleCondition);
        
        return [
            'success' => true,
            'message' => 'Loan offers retrieved successfully',
            'vehicle_info' => [
                'make' => $vehicleCondition['make'],
                'model' => $vehicleCondition['model'],
                'estimated_value' => $vehicleValue
            ],
            'offers' => $offers,
            'total_offers' => count($offers)
        ];
    }
    
    private function estimateVehicleValue($make, $model) {
        $baseValues = [
            'Maruti Suzuki' => 600000,
            'Hyundai' => 800000,
            'Tata' => 700000,
            'Honda' => 900000,
            'Toyota' => 1200000,
            'BMW' => 4000000,
            'Mercedes' => 4500000,
            'Audi' => 4200000
        ];
        
        $baseValue = $baseValues[$make] ?? 500000;
        
        $variation = rand(-100000, 200000);
        return max(300000, $baseValue + $variation);
    }
    
    private function generateOffers($income, $vehicleValue, $vehicleCondition) {
        $maxLoanAmount = min($vehicleValue * 0.9, $income * 60);
        
        $offers = [];
        
        $banks = [
            ['name' => 'HDFC Bank', 'rate' => 8.5, 'processing_fee' => 2500],
            ['name' => 'ICICI Bank', 'rate' => 8.7, 'processing_fee' => 3000],
            ['name' => 'SBI', 'rate' => 8.2, 'processing_fee' => 2000],
            ['name' => 'Axis Bank', 'rate' => 8.9, 'processing_fee' => 3500],
            ['name' => 'Kotak Bank', 'rate' => 8.6, 'processing_fee' => 2800]
        ];
        
        foreach ($banks as $bank) {
            $loanAmount = $maxLoanAmount * (0.8 + (rand(0, 20) / 100));
            $tenure = rand(3, 7);
            $monthlyEmi = $this->calculateEmi($loanAmount, $bank['rate'], $tenure);
            
            if ($monthlyEmi <= ($income * 0.4)) {
                $offers[] = [
                    'bank_name' => $bank['name'],
                    'loan_amount' => round($loanAmount),
                    'interest_rate' => $bank['rate'],
                    'tenure_years' => $tenure,
                    'monthly_emi' => round($monthlyEmi),
                    'processing_fee' => $bank['processing_fee'],
                    'total_payable' => round($monthlyEmi * $tenure * 12),
                    'approval_probability' => rand(70, 95) . '%',
                    'offer_id' => md5($bank['name'] . $loanAmount . time())
                ];
            }
        }
        
        usort($offers, function($a, $b) {
            return $a['interest_rate'] <=> $b['interest_rate'];
        });
        
        return array_slice($offers, 0, 3);
    }
    
    private function calculateEmi($principal, $rate, $tenure) {
        $monthlyRate = ($rate / 100) / 12;
        $months = $tenure * 12;
        
        if ($monthlyRate == 0) {
            return $principal / $months;
        }
        
        $emi = ($principal * $monthlyRate * pow(1 + $monthlyRate, $months)) / 
               (pow(1 + $monthlyRate, $months) - 1);
        
        return $emi;
    }
}

?>