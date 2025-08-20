const ChatContainerImproved = {
  template: `
  <div class="p-4">
    <div class="max-w-4xl mx-auto">
      <div class="bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl overflow-hidden border border-white/20">
        <!-- Header -->
        <div class="chat-header">
          <div class="chat-title">
            <img src="/AI_Chatbot/v1/images/car-remove.png" alt="CarTradeTech Logo" class="chat-logo" />
            <div>
              <h6>AI Agent for Cars and Loans</h6>
            </div>
          </div>
          <div class="logout-button">
            <button class="btn btn-sm btn-outline-danger" @click="handleLogout">Logout</button>
          </div>
        </div>

        <!-- Messages Container -->
        <div class="h-[600px] overflow-y-auto p-4 space-y-6 bg-gradient-to-b from-gray-50/50 to-white" ref="messagesContainer">
          <div v-for="(message, index) in messages" :key="message.id">
            <message :message="message" />
            
            <!-- Dynamic Component Rendering -->
            <div v-if="shouldShowComponent(message, index)" class="mt-4">
              <component 
                :is="getComponentName(message.component)" 
                v-bind="getComponentProps(message)"
                @select="handleComponentAction"
                @send-otp="handleOTPSend"
                @verify-otp="handleOTPVerify"
                @submit="handleComponentSubmit"
                :disabled="isLoading"
                :is-loading="isLoading"
              />
            </div>
            
            <!-- Vehicle Details (Special Case) -->
            <div v-else-if="shouldShowVehicleDetails(message, index)">
              <vehicle-details 
                :vehicle="vehicleDetails" 
                @select="handleVehicleSubmit" 
                :disabled="isLoading" 
              />
            </div>
          </div>
          
          <!-- Loading Indicator -->
          <div v-if="isLoading" class="flex justify-center py-4">
            <div class="flex space-x-2">
              <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce"></div>
              <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
              <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
            </div>
          </div>
          
          <div ref="messagesEndRef"></div>
        </div>

        <!-- Chat Input -->
        <div v-if="canShowChatInput" class="p-4 border-top">
          <chat-input @send="handleSendMessage" :disabled="isLoading" />
        </div>
        
        <!-- Error Display -->
        <div v-if="errorMessage" class="p-4 bg-red-50 border-t border-red-200">
          <div class="flex items-center space-x-2 text-red-700">
            <icon name="alert-circle" class="w-5 h-5" />
            <span>{{ errorMessage }}</span>
            <button @click="clearError" class="ml-auto text-red-500 hover:text-red-700">
              <icon name="x" class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
  `,
  
  components: {
    'message': Message,
    'language-dropdown': LanguageDropdown,
    'pan-upload': PANUpload,
    'otp-verification': OTPVerification,
    'brand-selection': BrandSelection,
    'model-selection': ModelSelection,
    'user-info-form': UserInfoForm,
    'offer-card': OfferCard,
    'chat-input': ChatInput,
    'vehicle-details': VehicleDetails,
  },
  
  data() {
    return {
      messages: [
        { 
          id: 1, 
          from: 'bot', 
          type: 'text', 
          content: `Hi! I'm your Vehicle Loan Assistant. I'm here to help you find the best loan offers for your selected vehicle.`, 
          show: true 
        },
      ],
      currentStep: 'chat-input',
      isLoading: false,
      errorMessage: '',
      completedSteps: new Set(),
      idCounter: 2,
      sessionId: this.generateSessionId(),
      
      // Component data
      brands: [],
      models: [],
      offers: [],
      vehicleDetails: {},
      
      // User state
      userState: {
        mobile_number: '',
        otp_verified: false,
        pan_uploaded: false,
        user_info: {},
        vehicles: [],
        current_vehicle_index: 0
      },
      
      // Configuration
      config: {
        maxRetries: 3,
        retryDelay: 1000,
        requestTimeout: 30000
      }
    };
  },
  
  computed: {
    lastComponentIndex() {
      for (let i = this.messages.length - 1; i >= 0; i--) {
        const message = this.messages[i];
        if (message.component || this.hasVehicleData(message)) {
          return i;
        }
      }
      return -1;
    },
    
    canShowChatInput() {
      return this.currentStep === 'completed' || 
             this.currentStep === 'chat-input' || 
             this.completedSteps.has('offers');
    }
  },
  
  watch: {
    messages: {
      handler() {
        this.scrollToBottom();
      },
      deep: true
    }
  },
  
  methods: {
    // Utility Methods
    generateSessionId() {
      return Math.random().toString(36).substr(2, 16) + Date.now().toString(36);
    },
    
    scrollToBottom() {
      this.$nextTick(() => {
        this.$refs.messagesEndRef?.scrollIntoView({ behavior: 'smooth' });
      });
    },
    
    addMessage(message) {
      const newMessage = { 
        id: this.idCounter++, 
        ...message, 
        show: true,
        timestamp: new Date().toISOString()
      };
      this.messages.push(newMessage);
      return newMessage;
    },
    
    addTypingIndicator() {
      return this.addMessage({ 
        from: 'bot', 
        type: 'text', 
        content: '...', 
        show: true,
        isTyping: true
      });
    },
    
    removeTypingIndicator() {
      this.messages = this.messages.filter(msg => !msg.isTyping);
    },
    
    setError(message) {
      this.errorMessage = message;
      this.isLoading = false;
    },
    
    clearError() {
      this.errorMessage = '';
    },
    
    // Component Logic
    shouldShowComponent(message, index) {
      return message.component && index === this.lastComponentIndex;
    },
    
    shouldShowVehicleDetails(message, index) {
      return !message.component && 
             index === this.lastComponentIndex && 
             this.hasVehicleData(message);
    },
    
    hasVehicleData(message) {
      return message.data && 
             message.data.length > 0 && 
             Object.keys(this.vehicleDetails).length > 0;
    },
    
    getComponentName(componentType) {
      const componentMap = {
        'language-dropdown': 'language-dropdown',
        'pan-upload': 'pan-upload',
        'mobile-input': 'otp-verification',
        'otp-input': 'otp-verification',
        'brand-selection': 'brand-selection',
        'model-selection': 'model-selection',
        'user-info-form': 'user-info-form',
        'offers': 'offer-card'
      };
      return componentMap[componentType] || componentType;
    },
    
    getComponentProps(message) {
      const props = { disabled: this.isLoading, 'is-loading': this.isLoading };
      
      switch (message.component) {
        case 'mobile-input':
          props.step = 'mobile';
          break;
        case 'otp-input':
          props.step = 'otp';
          break;
        case 'brand-selection':
          props.brands = this.brands;
          break;
        case 'model-selection':
          props.models = this.models;
          break;
        case 'offers':
          props.offers = this.offers;
          break;
      }
      
      return props;
    },
    
    // API Communication
    async sendToWebhook(data, retryCount = 0) {
      this.clearError();
      
      try {
        const payload = {
          sessionId: this.sessionId,
          action: 'sendMessage',
          chatInput: data.content,
        };

        const response = await axios.post('', payload, {
          timeout: this.config.requestTimeout,
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        if (response.status !== 200) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return this.processApiResponse(response.data);
        
      } catch (error) {
        console.error('API Error:', error);
        
        if (retryCount < this.config.maxRetries) {
          await new Promise(resolve => setTimeout(resolve, this.config.retryDelay));
          return this.sendToWebhook(data, retryCount + 1);
        }
        
        throw error;
      }
    },
    
    processApiResponse(responseData) {
      if (typeof responseData === 'string') {
        throw new Error('Invalid response format');
      }
      
      if (!responseData.status || responseData.status !== 'Ok') {
        const errorMsg = responseData.error?.error || 'Unknown error occurred';
        throw new Error(errorMsg);
      }
      
      let jsonResponse = responseData.details;
      
      // Parse JSON string if needed
      if (typeof jsonResponse === 'string') {
        const jsonString = jsonResponse.replace(/```json|```/g, '').trim();
        jsonResponse = JSON.parse(jsonString);
      }
      
      // Update user state
      if (responseData.state) {
        this.userState = { ...this.userState, ...responseData.state };
      }
      
      return this.formatApiResponse(jsonResponse);
    },
    
    formatApiResponse(jsonResponse) {
      const formattedResponse = {
        from: jsonResponse.from || 'bot',
        type: (jsonResponse.type || 'text').toLowerCase(),
        content: jsonResponse.content || '',
        component: jsonResponse.component || '',
        show: jsonResponse.show !== undefined ? jsonResponse.show : true,
        data: jsonResponse.data || [],
        mcp_result: jsonResponse.mcp_result
      };
      
      // Process component-specific data
      this.processComponentData(formattedResponse);
      
      return formattedResponse;
    },
    
    processComponentData(response) {
      if (!response.data || response.data.length === 0) return;
      
      const data = response.data;
      let transformedData = [];
      
      // Transform data based on structure
      if (typeof data[0] === 'object' && data[0].make) {
        transformedData = data.map(item => item.make);
      } else if (typeof data[0] === 'object' && data[0].name) {
        transformedData = data.map(item => item.name);
      } else if (typeof data[0] === 'string') {
        transformedData = data;
      }
      
      // Update component data based on component type
      switch (response.component) {
        case 'brand-selection':
          this.brands = transformedData;
          break;
        case 'model-selection':
          this.models = transformedData;
          break;
        case 'offers':
          this.offers = Array.isArray(data) ? data : [];
          break;
        default:
          if (transformedData.length > 0) {
            this.vehicleDetails = transformedData;
          }
      }
    },
    
    // Event Handlers
    async handleSendMessage(message) {
      if (!message.trim() || this.isLoading) return;
      
      this.addMessage({ from: 'user', type: 'text', content: message });
      this.isLoading = true;
      
      const typingMessage = this.addTypingIndicator();
      
      try {
        const response = await this.sendToWebhook({ 
          from: 'user', 
          type: 'text', 
          content: message 
        });
        
        this.removeTypingIndicator();
        this.addMessage(response);
        
      } catch (error) {
        this.removeTypingIndicator();
        this.setError(error.message);
        this.addMessage({ 
          from: 'bot', 
          type: 'text', 
          content: 'I apologize, but I encountered an error. Please try again.', 
          show: true 
        });
      } finally {
        this.isLoading = false;
      }
    },
    
    async handleComponentAction(data) {
      await this.processUserAction(data);
    },
    
    async handleComponentSubmit(data) {
      await this.processUserAction(data);
    },
    
    async handleOTPSend(mobile) {
      await this.processUserAction(`Mobile: ${mobile}`);
    },
    
    async handleOTPVerify(otp) {
      await this.processUserAction(`OTP: ${otp}`);
    },
    
    async handleVehicleSubmit(vehicle) {
      await this.processUserAction(`Selected Vehicle: ${vehicle}`);
    },
    
    async processUserAction(content) {
      if (this.isLoading) return;
      
      this.addMessage({ from: 'user', type: 'text', content });
      this.isLoading = true;
      
      const typingMessage = this.addTypingIndicator();
      
      try {
        const response = await this.sendToWebhook({ 
          from: 'user', 
          type: 'text', 
          content 
        });
        
        this.removeTypingIndicator();
        this.addMessage(response);
        
        // Update current step based on response
        this.updateCurrentStep(response);
        
      } catch (error) {
        this.removeTypingIndicator();
        this.setError(error.message);
      } finally {
        this.isLoading = false;
      }
    },
    
    updateCurrentStep(response) {
      if (response.component) {
        this.currentStep = response.component;
      } else if (response.type === 'text' && !response.component) {
        this.currentStep = 'chat-input';
      }
      
      // Mark steps as completed
      const stepMap = {
        'mobile-input': 'mobile',
        'otp-input': 'otp',
        'brand-selection': 'brand',
        'model-selection': 'model',
        'user-info-form': 'userInfo',
        'offers': 'offers'
      };
      
      if (stepMap[response.component]) {
        this.completedSteps.add(stepMap[response.component]);
      }
    },
    
    handleLogout() {
      if (confirm('Are you sure you want to logout? Your progress will be lost.')) {
        // Clear session data
        this.messages = [this.messages[0]]; // Keep welcome message
        this.userState = {
          mobile_number: '',
          otp_verified: false,
          pan_uploaded: false,
          user_info: {},
          vehicles: [],
          current_vehicle_index: 0
        };
        this.completedSteps.clear();
        this.currentStep = 'chat-input';
        this.sessionId = this.generateSessionId();
        this.clearError();
      }
    }
  },
  
  mounted() {
    this.scrollToBottom();
    
    // Add error handling for unhandled promise rejections
    window.addEventListener('unhandledrejection', (event) => {
      console.error('Unhandled promise rejection:', event.reason);
      this.setError('An unexpected error occurred. Please refresh the page.');
    });
  },
  
  beforeUnmount() {
    window.removeEventListener('unhandledrejection', this.handleUnhandledRejection);
  }
};
