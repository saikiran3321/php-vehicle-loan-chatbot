const ChatContainer = {
  template: `
  <div class="p-4">
    <div class="max-w-4xl mx-auto">
      <div class="bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl overflow-hidden border border-white/20">
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

        <div class="h-[600px] overflow-y-auto p-4 space-y-6 bg-gradient-to-b from-gray-50/50 to-white" ref="messagesContainer">
          <div v-for="(message, index) in messages" :key="message.id">
            <message :message="message" />
            <div v-if="message.component && index === lastComponentIndex" class="mt-4">
              <language-dropdown v-if="message.component === 'language-dropdown'" @select="handleLanguageSelect" :disabled="isLoading" />
              <pan-upload v-if="message.component === 'pan-upload'" @submit="handlePANSubmit" :is-loading="isLoading" :disabled="isLoading" />
              <otp-verification v-if="message.component === 'mobile-input'" step="mobile" @send-otp="handleOTPSend" :is-loading="isLoading" :disabled="isLoading" />
              <otp-verification v-if="message.component === 'otp-input'" step="otp" @verify-otp="handleOTPVerify" :is-loading="isLoading" :disabled="isLoading" />
              <brand-selection v-if="message.component === 'brand-selection'" :brands="brands" @select="handleBrandSelect" :disabled="isLoading" />
              <model-selection v-if="message.component === 'model-selection'" :models="models" @select="handleModelSelect" :disabled="isLoading" />
              <user-info-form v-if="message.component === 'user-info-form'" @submit="handleUserInfoSubmit" :is-loading="isLoading" :disabled="isLoading" />
              <div v-if="message.component === 'offers'" class="space-y-3">
                <offer-card v-for="(offer, idx) in offers" :key="idx" :offer="offer" />
              </div>
            </div>
            <div v-else-if="!message.component && index === lastComponentIndex">
              <vehicle-details v-if="Object.keys(vehicleDtls).length > 0 && message.data && message.data.length > 0" :vehicle="vehicleDtls" @select="handleVehicleSubmit" :disabled="isLoading" />
            </div>
          </div>
          <div ref="messagesEndRef"></div>
        </div>

        <div v-if="currentStep === 'completed' || currentStep ==='chart-input'" class="p-4 border-top">
          <chat-input @send="handleSendMessage" />
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
        { id: 1,  from: 'bot', type: 'text', content: `Hi! I'm your Vehicle Loan Assistant, I'm here to help you find the best loan offers for you selected vehicle`, show: true  },
      ],
      currentStep: 'chart-input',
      userInput: '',
      selectedLanguage: '',
      mobileNumber: '',
      otp: '',
      brands: [],
      models: [],
      selectedBrand: '',
      selectedModel: '',
      userInfo: { name: '', dob: '', pan: '', income: '', residence: '', employment: '' },
      offers: [],
      isLoading: false,
      completedSteps: new Set(),
      idCounter: 2,
      sessionId: this.generateSessionId(),
      vehicleDtls: {},
    };
  },
  computed: {
    lastComponentIndex() {
      for (let i = this.messages.length - 1; i >= 0; i--) {
        const m = this.messages[i];
        if (m.component) return i;
        if (!m.component && m.data && m.data.length > 0 && Object.keys(this.vehicleDtls).length > 0) return i;
      }
      return -1;
    }
  },
  watch: {
    messages() {
      this.scrollToBottom();
    }
  },
  methods: {
    handleLogout() {},
    scrollToBottom() {
      this.$nextTick(() => {
        this.$refs.messagesEndRef?.scrollIntoView({ behavior: 'smooth' });
      });
    },
    addMessage(message) {
      this.messages.push({ id: this.idCounter++, ...message, show: true  });
    },
    addTypingIndicator() {
      this.addMessage({ from: 'bot', type: 'text', content: '...', show: true  });
    },
    removeTypingIndicator() {
      this.messages = this.messages.filter(msg => msg.content !== '...');
    },
    generateSessionId() {
      return Math.random().toString(36).substr(2, 16);
    },
    async sendToWebhook(data) {
      this.vehicleDtls = {};
      let user_data = data.content;
      /*if(typeof user_data != "string") {
        user_data = JSON.stringify(user_data);
      }*/
      const payload = {
        sessionId: this.sessionId,
        action: 'sendMessage',
        chatInput: user_data,
      };

      await axios.post("",payload).then(response => {
        if(response.status === 200) {
          if(typeof (response.data) != "string") {
            if("status" in response.data && response.data.status == "Ok") {
              let jsonResponse;
              if (typeof response.data.details === "string") {
                const jsonString = response.data.details.replace(/```json|```/g, '').trim();
                jsonResponse = JSON.parse(jsonString);
              } else {
                jsonResponse = response.data.details;
              }
              this.removeTypingIndicator();

              const formattedResponse = {
                from: jsonResponse.from || 'bot',
                type: (jsonResponse.type || 'text').toLowerCase(),
                content: jsonResponse.content || '',
                component: jsonResponse.component || '',
                show: jsonResponse.show !== undefined ? jsonResponse.show : true,
                data: jsonResponse.data || [],
              };

              if(jsonResponse.data && jsonResponse.data.length > 0){
                let vehicleDtls = [];

                if (typeof jsonResponse.data[0] === 'object' && jsonResponse.data[0].make) {
                  vehicleDtls = jsonResponse.data.map(item => item.make);
                } else if(typeof jsonResponse.data[0] === 'object' && jsonResponse.data[0].name) {
                  vehicleDtls = jsonResponse.data.map(item => item.name);
                } else if (typeof jsonResponse.data[0] === 'string') {
                  vehicleDtls = jsonResponse.data;
                }

                this.vehicleDtls = vehicleDtls;
              }

              if(data.content.includes('OTP:')){
                if(jsonResponse.data && jsonResponse.data.length > 0){
                  let transformedBrands = [];

                  if (typeof jsonResponse.data[0] === 'object' && jsonResponse.data[0].make) {
                    transformedBrands = jsonResponse.data.map(item => item.make);
                  } else if(typeof jsonResponse.data[0] === 'object' && jsonResponse.data[0].name) {
                    transformedBrands = jsonResponse.data.map(item => item.name);
                  } else if (typeof jsonResponse.data[0] === 'string') {
                    transformedBrands = jsonResponse.data;
                  }
                  this.brands = transformedBrands;
                }
              } else if(jsonResponse.component == "brand-selection"){
                if(jsonResponse.data && jsonResponse.data.length > 0){
                  let transformedBrands = [];

                  if (typeof jsonResponse.data[0] === 'object' && jsonResponse.data[0].make) {
                    transformedBrands = jsonResponse.data.map(item => item.make);
                  } else if(typeof jsonResponse.data[0] === 'object' && jsonResponse.data[0].name) {
                    transformedBrands = jsonResponse.data.map(item => item.name);
                  } else if (typeof jsonResponse.data[0] === 'string') {
                    transformedBrands = jsonResponse.data;
                  }
                  this.brands = transformedBrands;
                }
              }

              if(data.content.includes('Selected Brand:')){
                if(jsonResponse.data && jsonResponse.data.length > 0){
                  let transformedModels = [];

                  if (typeof jsonResponse.data[0] === 'object' && jsonResponse.data[0].model) {
                    transformedModels = jsonResponse.data.map(item => item.model);
                  } else if(typeof jsonResponse.data[0] === 'object' && jsonResponse.data[0].name) {
                    transformedModels = jsonResponse.data.map(item => item.name);
                  } else if (typeof jsonResponse.data[0] === 'string') {
                    transformedModels = jsonResponse.data;
                  }

                  this.models = transformedModels;
                }
              } else if(jsonResponse.component == "model-selection"){
                if(jsonResponse.data && jsonResponse.data.length > 0){
                  let transformedModels = [];

                  if (typeof jsonResponse.data[0] === 'object' && jsonResponse.data[0].model) {
                    transformedModels = jsonResponse.data.map(item => item.model);
                  } else if(typeof jsonResponse.data[0] === 'object' && jsonResponse.data[0].name) {
                    transformedModels = jsonResponse.data.map(item => item.name);
                  } else if (typeof jsonResponse.data[0] === 'string') {
                    transformedModels = jsonResponse.data;
                  }

                  this.models = transformedModels;
                }
              }

              this.addMessage(formattedResponse);
            } else {
              this.removeTypingIndicator();
              this.addMessage({ from: 'bot', type: 'text', content: 'An error occurred while processing your request. Please try again later.', show: true });      
            }
          } else {
            this.removeTypingIndicator();
            this.addMessage({ from: 'bot', type: 'text', content: 'An error occurred while processing your request. Please try again later.', show: true });    
          }
        } else {
          this.removeTypingIndicator();
          this.addMessage({ from: 'bot', type: 'text', content: 'An error occurred while processing your request. Please try again later.', show: true });
        }
      }).catch(error => {
        console.error('There was a problem with the fetch operation or JSON parsing:', error);
        this.removeTypingIndicator();
        this.addMessage({ from: 'bot', type: 'text', content: 'An error occurred while processing your request. Please try again later.', show: true });
      })
    },
    handleVehicleSubmit(vehicle) {
      this.completedSteps.add('vehicle');
      this.addMessage({ from: 'user', type: 'text', content: `Selected Vehicle: ${vehicle}`, show: true });
      this.addTypingIndicator();
      this.sendToWebhook({ from: 'user', type: 'text', content: `Selected Vehicle: ${vehicle}`, show: true })
      .then(() => {
        this.currentStep = 'chart-input';
      })
    },
    handleLanguageSelect(language) {
      this.selectedLanguage = language;
      this.completedSteps.add('language');
      this.addMessage({ from: 'user', type: 'text', content: `Selected: ${language}`, show: true });
      this.addTypingIndicator();
      this.sendToWebhook({ from: 'user', type: 'text', content: `Selected: ${language}`, show: true })
      .then(() => {
        this.currentStep = 'pan-upload';
      })
      .catch((error) => {
        console.error('Error sending to webhook:', error);
      });
    },
    handlePANSubmit(panData, file) {
      this.completedSteps.add('pan');
      this.addMessage({ from: 'user', type: 'text', content: JSON.stringify(panData), show: true  }); 
      this.addTypingIndicator();
      this.sendToWebhook({ from: 'user', type: 'text', content: panData, show: true  })
      .then(() => {
        this.currentStep = 'mobile-input';
      })
      .catch((error) => {
        console.error('Error sending to webhook:', error);
      });
    },
    handleOTPSend(mobile) {
      this.mobileNumber = mobile;
      this.completedSteps.add('mobile');
      this.addMessage({ from: 'user', type: 'text', content: `Mobile: ${mobile}`, show: true  });
      this.addTypingIndicator();
      this.sendToWebhook({ from: 'user', type: 'text', content: `Mobile: ${mobile}`, show: true  })
      .then(() => {
        this.currentStep = 'otp-input';
      })
      .catch((error) => {
        console.error('Error sending to webhook:', error);
      });
    },
    handleOTPVerify(otpCode) {
      this.otp = otpCode;
      this.completedSteps.add('otp');
      this.addMessage({ from: 'user', type: 'text', content: `OTP: ${otpCode}` , show: true });
      this.addTypingIndicator();
      this.sendToWebhook({ from: 'user', type: 'text', content: `OTP: ${otpCode}` , show: true })
      .then(() => {
        this.currentStep = 'brand-selection';
      })
      .catch((error) => {
        console.error('Error sending to webhook:', error);
      });
    },
    handleBrandSelect(brand) {
      this.selectedBrand = brand;
      this.completedSteps.add('brand');
      this.addMessage({ from: 'user', type: 'text', content: `Selected Brand: ${brand}` , show: true });
      const mockModels = {
        'Toyota': ['Camry', 'Corolla', 'Fortuner', 'Innova'],
        'Honda': ['City', 'Civic', 'CR-V', 'Accord'],
        'Maruti Suzuki': ['Swift', 'Baleno', 'Vitara Brezza', 'Dzire'],
        'Hyundai': ['i20', 'Creta', 'Verna', 'Tucson'],
        'Tata': ['Nexon', 'Harrier', 'Safari', 'Altroz'],
        'Mahindra': ['XUV700', 'Scorpio', 'Thar', 'XUV300']
      };
      this.models = mockModels[brand] || [];
      this.addTypingIndicator();
      this.sendToWebhook({ from: 'user', type: 'text', content: `Selected Brand: ${brand}` , show: true })
      .then(() => {
        this.currentStep = 'model-selection';
      })
      .catch((error) => {
        console.error('Error sending to webhook:', error);
      });
    },
    handleModelSelect(model) {
      this.selectedModel = model;
      this.completedSteps.add('model');
      this.addMessage({ from: 'user', type: 'text', content: `Selected Model: ${model}`, show: true  });
      this.addTypingIndicator();
      this.sendToWebhook({ from: 'user', type: 'text', content: `Selected Model: ${model}` , show: true })
      .then(() => {
        this.currentStep = 'user-info-form';
      })
      .catch((error) => {
        console.error('Error sending to webhook:', error);
      });
    },
    handleUserInfoSubmit(info) {
      this.userInfo = info;
      this.completedSteps.add('userInfo');
      this.addMessage({ from: 'user', type: 'text', content: JSON.stringify(this.userInfo), show: true  });
      this.addTypingIndicator();
      this.sendToWebhook({ from: 'user', type: 'text', content: this.userInfo , show: true })
      .then(() => {
        const mockOffers = [
          { bankName: 'HDFC Bank', status: 'Pre-approved', interestRate: 7.5, amount: 500000 },
          { bankName: 'ICICI Bank', status: 'Approved', interestRate: 8.0, amount: 450000 },
          { bankName: 'SBI', status: 'Under Review', interestRate: 7.8, amount: 400000 }
        ];
        this.offers = mockOffers;
        this.currentStep = 'completed';
      })
      .catch((error) => {
        console.error('Error sending to webhook:', error);
      });
    },
    handleSendMessage(message) {
      if (!message.trim()) return;
      this.addMessage({ from: 'user', type: 'text', content: message, show: true  });
      this.addTypingIndicator();
      setTimeout(() => {
        this.sendToWebhook({ from: 'user', type: 'text', content: message, show: true });
      }, 1500);
    }
  },
  mounted() {
    this.scrollToBottom();
  }
};
