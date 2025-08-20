<?php

namespace VehicleLoanAssistant;

use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\McpResource;
use PhpMcp\Server\Attributes\McpPrompt;
use PhpMcp\Server\Attributes\Schema;
use ReflectionMethod;

/**
 * Vehicle Loan MCP Server - Provides tools and resources for vehicle loan processing
 */
class VehicleLoanMcpServer
{
    private $mongodb_con;
    
    public function __construct()
    {
        global $mongodb_con;
        $this->mongodb_con = $mongodb_con;
    }

    #[McpTool(
        name: 'send_otp',
        description: 'Send OTP to mobile number for verification'
    )]
    #[Schema(
        properties: [
            'data' => [
                'type' => 'object', 
                'properties' => [
                    'mobile_number' => ['type' => 'string', 'description' => '10-digit mobile number', 'pattern' => '^\d{10}$']
                ],
                'required' => ['mobile_number']
            ]
        ], 
        required: ['data'],
    )]
    public function sendOtp($data)
    {
        if (empty($data['mobile_number']) || !preg_match('/^\d{10}$/', $data['mobile_number'])) {
            return [
                'success' => false,
                'error' => 'Invalid mobile number format. Please provide 10-digit mobile number.'
            ];
        }

        $otp = rand(100000, 999999);
        
        $otpData = [
            'mobile_number' => $data['mobile_number'],
            'otp' => (string)$otp,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
            'verified' => false,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->mongodb_con->insert('ai_otp_verification', $otpData);
        
        return [
            'success' => true,
            'message' => 'OTP sent successfully to ' . $data['mobile_number'],
            'otp' => $otp,
            'expires_in' => '5 minutes'
        ];
    }

    #[McpTool(
        name: 'verify_otp',
        description: 'Verify OTP for mobile number'
    )]
    #[Schema(
        properties: [
            'data' => [
                'type' => 'object', 
                'properties' => [
                    'mobile_number' => ['type' => 'string', 'description' => '10-digit mobile number', 'pattern' => '^\d{10}$'], 
                    'otp' => ['type' => 'string', 'description' => '6-digit OTP', 'pattern' => '^\d{6}$']
                ],
                'required' => ['mobile_number','otp']
            ]
        ],
        required : ['data']
    )]
    public function verifyOtp($data)
    {
        if (empty($data['mobile_number']) || !preg_match('/^\d{10}$/', $data['mobile_number'])) {
            return [
                'success' => false,
                'error' => 'Invalid mobile number format'
            ];
        }

        if (empty($data['otp']) || !preg_match('/^\d{6}$/', $data['otp'])) {
            return [
                'success' => false,
                'error' => 'Invalid OTP format'
            ];
        }

        $otpRecord = $this->mongodb_con->find_one('ai_otp_verification', [
            'mobile_number' => $data['mobile_number'],
            'otp' => $data['otp'],
            'verified' => false
        ]);

        if (!$otpRecord) {
            return [
                'success' => false,
                'error' => 'Invalid or expired OTP'
            ];
        }

        if (strtotime($otpRecord['expires_at']) < time()) {
            return [
                'success' => false,
                'error' => 'OTP has expired. Please request a new one.'
            ];
        }

        $this->mongodb_con->update_one(
            'ai_otp_verification',
            ['$set' => ['verified' => true, 'verified_at' => date('Y-m-d H:i:s')]],
            ['_id' => $otpRecord['_id']]
        );

        return [
            'success' => true,
            'message' => 'OTP verified successfully',
            'state_updates' => [
                'otp_verified' => true,
                'mobile_verified' => true
            ]
        ];
    }

    #[McpTool(
        name: 'request_pan_details',
        description: 'Capture PAN card details (Name, DOB, PAN Number) from the user'
    )]
    #[Schema(
        properties: [
            'data' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Full name as per PAN card'],
                    'dob' => ['type' => 'string', 'description' => 'Date of Birth (DD-MM-YYYY)', 'pattern' => '^\d{2}-\d{2}-\d{4}$'],
                    'pan_number' => ['type' => 'string', 'description' => '10-character PAN number', 'pattern' => '^[A-Z]{5}[0-9]{4}[A-Z]$']
                ],
                'required' => ['name','dob','pan_number']
            ],
        ],
        required : ['data']
    )]
    public function requestPanDetails($data)
    {

        if (empty($data['name']) || !preg_match('/^[a-zA-Z\s]+$/', $data['name'])) {
            return ['success' => false, 'error' => 'Invalid name format.'];
        }
        if (empty($data['dob']) || !preg_match('/^\d{2}-\d{2}-\d{4}$/', $data['dob'])) {
            return ['success' => false, 'error' => 'Invalid Date of Birth format. Please use DD-MM-YYYY.'];
        }
        if (empty($data['pan_number']) || !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $data['pan_number'])) {
            return ['success' => false, 'error' => 'Invalid PAN number format.'];
        }

        return [
            'success' => true,
            'message' => 'PAN details captured successfully.',
            'state_updates' => [
                'pan_details_entered' => true
            ]
        ];
    }

    #[McpTool(
        name: 'search_brands',
        description: 'Search for vehicle brands/makes'
    )]
    #[Schema(
        properties: [
            'data' => [
                'type' => 'object',
                'properties' => [
                    'make' => ['type' => 'string', 'description' => 'Search query']
                ],
                'required' => ['make']
            ]
        ],
        required: ['data']
    )]
    public function searchBrands($data)
    {
        $brands = [
            'Maruti Suzuki', 'Hyundai', 'Tata', 'Mahindra', 'Toyota', 
            'Honda', 'Ford', 'Volkswagen', 'Skoda', 'Renault',
            'Nissan', 'Kia', 'MG', 'Jeep', 'BMW', 'Mercedes-Benz',
            'Audi', 'Bajaj', 'TVS', 'Hero', 'Royal Enfield', 'Yamaha'
        ];

        $query = $data['make'];

        if (!empty($query)) {
            $brands = array_filter($brands, function($brand) use ($query) {
                return stripos($brand, $query) !== false;
            });
        }

        return [
            'success' => true,
            'data' => array_map(function($brand) {
                return ['make' => $brand];
            }, array_values($brands)),
            'total' => count($brands)
        ];
    }

    #[McpTool(
        name: 'search_models',
        description: 'Search for vehicle models by brand'
    )]
    #[Schema(
        properties: [
            'data' => [
                'type' => 'object',
                'properties' => [
                    'make' => ['type' => 'string', 'description' => 'Vehicle make'], 
                    'model' => ['type' => 'string', 'description' => 'Search query']
                ],
                'required' => ['make','model']
            ]
        ],
        required:['data']
    )]
    public function searchModels($data)
    {
        if (empty($data['make'])) {
            return [
                'success' => false,
                'error' => 'Make/brand is required for model search'
            ];
        }

        // Sample models data (replace with actual database)
        $modelsByMake = [
            'Maruti Suzuki' => ['Alto', 'Swift', 'Baleno', 'Dzire', 'Ertiga', 'Vitara Brezza', 'S-Cross'],
            'Hyundai' => ['i10', 'i20', 'Verna', 'Creta', 'Tucson', 'Santro'],
            'Tata' => ['Tiago', 'Tigor', 'Nexon', 'Harrier', 'Safari'],
            'Honda' => ['City', 'Amaze', 'Jazz', 'CR-V', 'Civic'],
            'Toyota' => ['Innova', 'Fortuner', 'Camry', 'Glanza', 'Urban Cruiser']
        ];

        $make = $data['make'];
        $models = $modelsByMake[$make] ?? [];

        $query = $data['model'];

        if (!empty($query) && !empty($models)) {
            $models = array_filter($models, function($model) use ($query) {
                return stripos($model, $query) !== false;
            });
        }

        return [
            'success' => true,
            'data' => array_map(function($model) use ($make) {
                return ['model' => $model, 'make' => $make];
            }, array_values($models)),
            'total' => count($models)
        ];
    }

    #[McpTool(
        name: 'compare_vehicles',
        description: 'Compare multiple vehicles for loan eligibility and features'
    )]
    #[Schema(
        properties: [
            'data' => [
                'type' => 'object',
                'properties' => [
                    'vehicles' => [
                        'type' => 'array',
                        'description' => 'Array of vehicles to compare (minimum 2)',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'make' => ['type' => 'string'],
                                'model' => ['type' => 'string'],
                                'variant' => ['type' => 'string'],
                                'price' => ['type' => 'number']
                            ]
                        ]
                    ],
                    'comparison_criteria' => [
                        'type' => 'array',
                        'description' => 'Criteria for comparison (price, features, loan terms)',
                        'items' => ['type' => 'string']
                    ]
                ],
                'required' => ['vehicles']
            ]
        ],
        required: ['data']
    )]
    public function compareVehicles($data)
    {
        if (empty($data['vehicles']) || !is_array($data['vehicles']) || count($data['vehicles']) < 2) {
            return [
                'success' => false,
                'error' => 'At least 2 vehicles are required for comparison'
            ];
        }

        $vehicles = $data['vehicles'];
        $comparison = [];

        foreach ($vehicles as $index => $vehicle) {
            $make = $vehicle['make'] ?? 'Unknown';
            $model = $vehicle['model'] ?? 'Unknown';
            $variant = $vehicle['variant'] ?? 'Standard';
            $price = $vehicle['price'] ?? 0;

            $comparison[] = [
                'index' => $index,
                'make' => $make,
                'model' => $model,
                'variant' => $variant,
                'price' => $price,
                'price_formatted' => '₹' . number_format($price, 0),
                'loan_amount' => $price * 0.8, // 80% loan amount
                'down_payment' => $price * 0.2, // 20% down payment
                'emi_36_months' => $this->calculateEMI($price * 0.8, 10.5, 36),
                'emi_60_months' => $this->calculateEMI($price * 0.8, 10.5, 60),
                'features' => $this->getVehicleFeatures($make, $model)
            ];
        }

        return [
            'success' => true,
            'comparison' => $comparison,
            'summary' => [
                'total_vehicles' => count($vehicles),
                'price_range' => [
                    'min' => min(array_column($vehicles, 'price')),
                    'max' => max(array_column($vehicles, 'price'))
                ],
                'recommendation' => $this->getRecommendation($comparison)
            ]
        ];
    }

    #[McpTool(
        name: 'assess_loan_eligibility',
        description: 'Assess loan eligibility for selected vehicle based on user profile'
    )]
    #[Schema(
        properties: [
            'data' => [
                'type' => 'object',
                'properties' => [
                    'vehicle_selected' => [
                        'type' => 'object',
                        'description' => 'Selected vehicle details',
                        'properties' => [
                            'make' => ['type' => 'string'],
                            'model' => ['type' => 'string'],
                            'price' => ['type' => 'number']
                        ]
                    ],
                    'user_income' => [
                        'type' => 'number',
                        'description' => 'Monthly/annual income'
                    ],
                    'employment_type' => [
                        'type' => 'string',
                        'description' => 'Type of employment (salaried, self-employed, business)'
                    ],
                    'credit_score' => [
                        'type' => 'number',
                        'description' => 'Credit score if available'
                    ],
                    'down_payment' => [
                        'type' => 'number',
                        'description' => 'Available down payment amount'
                    ]
                ],
                'required' => ['vehicle_selected', 'user_income', 'employment_type']
            ]
        ],
        required: ['data']
    )]
    public function assessLoanEligibility($data)
    {
        if (empty($data['vehicle_selected']) || empty($data['user_income']) || empty($data['employment_type'])) {
            return [
                'success' => false,
                'error' => 'Vehicle details, income, and employment type are required'
            ];
        }

        $vehicle = $data['vehicle_selected'];
        $monthlyIncome = $data['user_income'];
        $employmentType = $data['employment_type'];
        $creditScore = $data['credit_score'] ?? 650;
        $downPayment = $data['down_payment'] ?? 0;

        $vehiclePrice = $vehicle['price'] ?? 0;
        $loanAmount = $vehiclePrice - $downPayment;
        $monthlyEMI = $this->calculateMonthlyEMI($loanAmount, $creditScore);

        $eligibility = [
            'eligible' => $monthlyEMI <= ($monthlyIncome * 0.4),
            'vehicle_price' => $vehiclePrice,
            'loan_amount' => $loanAmount,
            'down_payment_required' => max($vehiclePrice * 0.1, 50000),
            'monthly_emi' => $monthlyEMI,
            'max_loan_amount' => $monthlyIncome * 20,
            'interest_rate' => $this->getInterestRate($creditScore, $employmentType),
            'tenure_options' => [12, 24, 36, 48, 60],
            'emi_options' => $this->getEMIOptions($loanAmount, $creditScore, $employmentType),
            'recommendations' => $this->getLoanRecommendations($monthlyIncome, $creditScore, $vehiclePrice)
        ];

        return [
            'success' => true,
            'eligibility' => $eligibility
        ];
    }

    #[McpTool(
        name: 'save_user',
        description: 'Save user information for loan application'
    )]
    #[Schema(
        properties: [
            'data' => ['type' => 'object', 
            'properties' => [
                'session_id' => ['type' => 'string'], 
                'name' => ['type' => 'string'], 
                'email' => ['type' => 'string', 'format' => 'email'], 
                'mobile_number' => ['type' => 'string', 'pattern' => '^\d{10}$'],
                'pan' => ['type' => 'string', 'pattern' => '^[A-Z]{5}[0-9]{4}[A-Z]$'],
                'otp_verified' => ['type' =>'boolean']
            ], 
            'required' => ['session_id', 'name', 'email', 'mobile_number','otp_verified']], 
        ],
        required: ['data']
    )]
    public function saveUser($data)
    {
        if (empty($data) || !is_array($data)) {
            return [
                'success' => false,
                'error' => 'User data is required'
            ];
        }

        // Validate required fields
        $requiredFields = ['session_id', 'name', 'email', 'mobile_number'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'error' => "Field '{$field}' is required"
                ];
            }
        }

        // Add timestamps
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        // Save to database
        $insertId = $this->mongodb_con->insert('loan_applications', $data);

        if ($insertId) {
            return [
                'success' => true,
                'message' => 'User information saved successfully',
                'application_id' => $insertId,
                'state_updates' => [
                    'user_info_saved' => true,
                    'application_id' => $insertId
                ]
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to save user information'
        ];
    }

    #[McpTool(
        name: 'fetch_offers',
        description: 'Fetch loan offers based on user and vehicle data'
    )]
    #[Schema(
        properties: [
            'data' => ['type' => 'object', 
            'properties' => [
                'loan_amount' => ['type' => 'integer'], 
                'tenure_months' => ['type' => 'integer'], 
                'interest_rate' => ['type' => 'number'], 
                'user_id' => ['type' => 'string'], 
                'vehicle_details' => [
                    'type' => 'object', 
                    'properties' => [
                        'make' => ['type' => 'string'], 
                        'model' => ['type' => 'string']
                    ]
                ]
            ], 
            'required' => ['loan_amount', 'tenure_months', 'user_id']], 
        ],
        required : ['data']
    )]
    public function fetchOffers($data)
    {
        if (empty($data) || !is_array($data)) {
            return [
                'success' => false,
                'error' => 'Loan data is required'
            ];
        }

        // Sample loan offers (replace with actual loan calculation logic)
        $offers = [
            [
                'bank_name' => 'SBI Auto Loan',
                'interest_rate' => '8.50%',
                'processing_fee' => '₹5,000',
                'max_tenure' => '7 years',
                'loan_to_value' => '90%',
                'eligibility' => 'Approved',
                'monthly_emi' => $this->calculateEMI($data['loan_amount'] ?? 500000, 8.50, 84),
                'features' => ['Quick approval', 'Minimal documentation', 'Pre-approved offers']
            ],
            [
                'bank_name' => 'HDFC Auto Loan',
                'interest_rate' => '8.75%',
                'processing_fee' => '₹3,500',
                'max_tenure' => '7 years',
                'loan_to_value' => '85%',
                'eligibility' => 'Approved',
                'monthly_emi' => $this->calculateEMI($data['loan_amount'] ?? 500000, 8.75, 84),
                'features' => ['Zero pre-closure charges', 'Flexible repayment', 'Digital processing']
            ],
            [
                'bank_name' => 'ICICI Auto Loan',
                'interest_rate' => '9.00%',
                'processing_fee' => '₹4,000',
                'max_tenure' => '6 years',
                'loan_to_value' => '80%',
                'eligibility' => 'Under Review',
                'monthly_emi' => $this->calculateEMI($data['loan_amount'] ?? 500000, 9.00, 72),
                'features' => ['Instant approval', 'Competitive rates', 'Easy documentation']
            ]
        ];

        return [
            'success' => true,
            'offers' => $offers,
            'total_offers' => count($offers),
            'comparison_url' => '/compare-offers',
            'message' => 'Found ' . count($offers) . ' loan offers for your requirement'
        ];
    }

    #[McpResource(
        uri: 'vehicle://brands',
        name: 'Vehicle Brands',
        description: 'List of available vehicle brands for loan applications',
        mimeType: 'application/json'
    )]
    public function getVehicleBrands(): array
    {
        return $this->searchBrands();
    }

    #[McpResource(
        uri: 'loan://eligibility-criteria',
        name: 'Loan Eligibility Criteria',
        description: 'Eligibility criteria for vehicle loans',
        mimeType: 'application/json'
    )]
    public function getLoanEligibilityCriteria(): array
    {
        return [
            'age' => [
                'minimum' => 21,
                'maximum' => 65,
                'description' => 'Age should be between 21 and 65 years'
            ],
            'income' => [
                'salaried_minimum' => 25000,
                'self_employed_minimum' => 50000,
                'description' => 'Minimum monthly income requirements'
            ],
            'employment' => [
                'salaried_experience' => '6 months',
                'self_employed_business_vintage' => '2 years',
                'description' => 'Employment/business stability requirements'
            ],
            'credit_score' => [
                'minimum' => 650,
                'preferred' => 750,
                'description' => 'Credit score requirements for approval'
            ],
            'documents_required' => [
                'identity_proof' => ['Aadhaar Card', 'PAN Card', 'Passport'],
                'address_proof' => ['Utility Bill', 'Bank Statement', 'Rent Agreement'],
                'income_proof' => ['Salary Slip', 'IT Returns', 'Bank Statements'],
                'employment_proof' => ['Employment Letter', 'Business Registration']
            ]
        ];
    }

    #[McpPrompt(
        name: 'loan_application_assistant',
        description: 'AI assistant prompt for vehicle loan applications',
        properties: ['user_query', 'user_context']
    )]
    public function getLoanApplicationPrompt(string $user_query, array $user_context = []): string
    {
        $prompt = "You are a helpful vehicle loan assistant. ";
        $prompt .= "User query: {$user_query}\n\n";
        
        if (!empty($user_context)) {
            $prompt .= "User context:\n";
            foreach ($user_context as $key => $value) {
                $prompt .= "- {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "Guidelines:\n";
        $prompt .= "1. Be helpful and professional\n";
        $prompt .= "2. Ask for missing information step by step\n";
        $prompt .= "3. Explain loan terms clearly\n";
        $prompt .= "4. Guide through the application process\n";
        $prompt .= "5. Provide accurate eligibility criteria\n";
        $prompt .= "6. Suggest suitable loan options\n\n";
        
        $prompt .= "Please provide a helpful response to assist with the vehicle loan application.";

        return $prompt;
    }

    /**
     * Helper method to calculate EMI
     */
    private function calculateEMI(float $principal, float $rate, int $tenure): string
    {
        $monthlyRate = ($rate / 100) / 12;
        $emi = ($principal * $monthlyRate * pow(1 + $monthlyRate, $tenure)) / (pow(1 + $monthlyRate, $tenure) - 1);
        return '₹' . number_format($emi, 0);
    }

    /**
     * Calculate monthly EMI for loan eligibility
     */
    private function calculateMonthlyEMI(float $principal, int $creditScore): float
    {
        $rate = $this->getInterestRate($creditScore, 'salaried');
        $monthlyRate = ($rate / 100) / 12;
        $tenure = 60; // 5 years default
        
        if ($monthlyRate == 0) return 0;
        
        $emi = ($principal * $monthlyRate * pow(1 + $monthlyRate, $tenure)) / (pow(1 + $monthlyRate, $tenure) - 1);
        return round($emi, 2);
    }

    /**
     * Get interest rate based on credit score and employment type
     */
    private function getInterestRate(int $creditScore, string $employmentType): float
    {
        $baseRate = 8.5;
        
        if ($creditScore >= 750) {
            $baseRate = 7.5;
        } elseif ($creditScore >= 650) {
            $baseRate = 9.5;
        } else {
            $baseRate = 12.5;
        }

        if ($employmentType === 'self-employed' || $employmentType === 'business') {
            $baseRate += 1.0;
        }

        return $baseRate;
    }

    /**
     * Get EMI options for different tenures
     */
    private function getEMIOptions(float $principal, int $creditScore, string $employmentType): array
    {
        $rate = $this->getInterestRate($creditScore, $employmentType);
        $tenures = [12, 24, 36, 48, 60];
        $options = [];

        foreach ($tenures as $tenure) {
            $monthlyRate = ($rate / 100) / 12;
            if ($monthlyRate == 0) continue;
            
            $emi = ($principal * $monthlyRate * pow(1 + $monthlyRate, $tenure)) / (pow(1 + $monthlyRate, $tenure) - 1);
            $options[] = [
                'tenure_months' => $tenure,
                'tenure_years' => $tenure / 12,
                'monthly_emi' => round($emi, 2),
                'total_amount' => round($emi * $tenure, 2),
                'interest_amount' => round(($emi * $tenure) - $principal, 2)
            ];
        }

        return $options;
    }

    /**
     * Get vehicle features for comparison
     */
    private function getVehicleFeatures(string $make, string $model): array
    {
        $features = [
            'Maruti Suzuki' => [
                'Alto' => ['Fuel Efficient', 'Low Maintenance', 'Good Resale Value'],
                'Swift' => ['Sporty Design', 'Good Mileage', 'Modern Features'],
                'Baleno' => ['Premium Features', 'Good Safety Rating', 'Comfortable Interior']
            ],
            'Hyundai' => [
                'i10' => ['Compact Design', 'Easy Parking', 'Good City Performance'],
                'i20' => ['Premium Hatchback', 'Advanced Features', 'Good Build Quality'],
                'Creta' => ['SUV Stance', 'Good Ground Clearance', 'Spacious Interior']
            ],
            'Tata' => [
                'Tiago' => ['5-Star Safety', 'Good Build Quality', 'Value for Money'],
                'Nexon' => ['Electric Vehicle', 'Zero Emissions', 'Advanced Technology'],
                'Harrier' => ['Premium SUV', 'Good Performance', 'Modern Design']
            ]
        ];

        return $features[$make][$model] ?? ['Standard Features', 'Good Performance', 'Reliable'];
    }

    /**
     * Get recommendation based on comparison
     */
    private function getRecommendation(array $comparison): array
    {
        if (empty($comparison)) return ['message' => 'No vehicles to compare'];

        $bestValue = null;
        $bestPrice = null;
        $bestFeatures = null;

        foreach ($comparison as $vehicle) {
            if (!$bestValue || $vehicle['price'] < $bestValue['price']) {
                $bestValue = $vehicle;
            }
            
            if (!$bestPrice || $vehicle['price'] < $bestPrice['price']) {
                $bestPrice = $vehicle;
            }
        }

        return [
            'best_value' => $bestValue,
            'best_price' => $bestPrice,
            'message' => "Based on the comparison, {$bestValue['make']} {$bestValue['model']} offers the best value for money."
        ];
    }

    /**
     * Get loan recommendations based on user profile
     */
    private function getLoanRecommendations(float $monthlyIncome, int $creditScore, float $vehiclePrice): array
    {
        $recommendations = [];

        if ($monthlyIncome < 25000) {
            $recommendations[] = 'Consider a smaller vehicle or increase down payment';
        }

        if ($creditScore < 650) {
            $recommendations[] = 'Improve credit score for better interest rates';
        }

        if ($vehiclePrice > ($monthlyIncome * 20)) {
            $recommendations[] = 'Vehicle price exceeds recommended loan amount';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Good profile for vehicle loan approval';
        }

        return $recommendations;
    }

    /**
     * Get all available MCP tools, resources, and prompts
     */
    public function getCapabilities(): array
    {
        return [
            'tools' => [
                'send_otp' => 'Send OTP for mobile verification',
                'verify_otp' => 'Verify mobile OTP',
                'request_pan_details' => 'Capture PAN details (Name, DOB, PAN Number)',
                'search_brands' => 'Search vehicle brands',
                'search_models' => 'Search vehicle models',
                'compare_vehicles' => 'Compare multiple vehicles for loan eligibility',
                'assess_loan_eligibility' => 'Assess loan eligibility for selected vehicle',
                'save_user' => 'Save user information',
                'fetch_offers' => 'Get loan offers',
                'get_all_mcp_tools' => 'List all MCP tools with schemas'
            ],
            'resources' => [
                'vehicle://brands' => 'Vehicle brands list',
                'loan://eligibility-criteria' => 'Loan eligibility information'
            ],
            'prompts' => [
                'loan_application_assistant' => 'AI assistant for loan applications'
            ]
        ];
    }

    /**
     * Get all defined MCP tools with their descriptions and schemas.
     */
    #[McpTool(name: 'get_all_mcp_tools', description: 'Get a list of all available MCP tools and their schemas')]
    #[Schema(properties: ['data' => ['type' => 'object', 'description' => 'Optional data payload']])]
    public function getAllMcpTools(array $data = []): array
    {
        $tools = [];
        $rc = new \ReflectionClass(self::class);

        foreach ($rc->getMethods() as $m) {
            $toolAttrs = $m->getAttributes(\PhpMcp\Server\Attributes\McpTool::class);
            if (!$toolAttrs) continue;

            /** @var \PhpMcp\Server\Attributes\McpTool $tool */
            $tool = $toolAttrs[0]->newInstance();

            $schema = null;
            $schemaAttrs = $m->getAttributes(\PhpMcp\Server\Attributes\Schema::class);
            if ($schemaAttrs) {
                $schema = $schemaAttrs[0]->newInstance()->toArray();
            }

            $tools[$tool->name] = [
                'description' => $tool->description,
                'schema'      => $schema,
            ];
        }

        return ['tools' => $tools];
    }

    public function callTool(string $toolName, array $payload = []): array
    {
        $method = $this->snakeToCamel($toolName);

        if (!method_exists($this, $method)) {
            return [
                'success' => false,
                'error' => "Tool '{$toolName}' not found (expected method {$method} on server)",
            ];
        }

        $arg = $payload['data'] ?? $payload ?? [];

        try {
            $result = $this->{$method}($arg);
            return is_array($result) ? $result : ['success' => true, 'result' => $result];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function snakeToCamel(string $name): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
    }
}
