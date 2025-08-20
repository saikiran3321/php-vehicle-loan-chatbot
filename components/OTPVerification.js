const OTPVerification = {
  props: ['step', 'isLoading', 'disabled'],
  emits: ['send-otp', 'verify-otp'],
  template: `
    <div v-if="step === 'mobile'" class="bg-white rounded-2xl p-8 shadow-xl border border-gray-100/50 backdrop-blur-sm" :class="{ 'opacity-60': disabled }">
      <div class="flex items-center gap-3 mb-6">
        <icon name="smartphone" class="w-6 h-6 text-blue-600"></icon>
        <span class="font-semibold text-lg text-gray-800">Mobile Verification</span>
      </div>
      <div class="space-y-6">
        <div>
          <label class="block text-base font-semibold text-gray-700 mb-3">Mobile Number</label>
          <input
            type="tel"
            v-model="mobileNumber"
            :disabled="disabled"
            placeholder="Enter your mobile number"
            class="w-full px-5 py-4 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-base disabled:opacity-50 disabled:cursor-not-allowed"
          />
        </div>
        <button
          @click="handleMobileSubmit"
          :disabled="!mobileNumber.trim() || isLoading || disabled"
          class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-4 px-6 rounded-xl hover:from-blue-700 hover:to-purple-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-3 font-semibold text-base shadow-lg hover:shadow-xl"
        >
          <div v-if="isLoading" class="w-6 h-6 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
          <icon v-else name="send" class="w-6 h-6"></icon>
          Send OTP
        </button>
      </div>
    </div>

    <div v-else class="bg-white rounded-2xl p-8 shadow-xl border border-gray-100/50 backdrop-blur-sm" :class="{ 'opacity-60': disabled }">
      <div class="flex items-center gap-3 mb-6">
        <icon name="shield" class="w-6 h-6 text-green-600"></icon>
        <span class="font-semibold text-lg text-gray-800">Enter OTP</span>
      </div>
      <div class="space-y-6">
        <div>
          <label class="block text-base font-semibold text-gray-700 mb-3">Verification Code</label>
          <input
            type="text"
            v-model="otp"
            @input="handleOTPChange"
            :disabled="disabled"
            placeholder="Enter 6-digit OTP"
            class="w-full px-5 py-4 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-center text-xl tracking-widest font-mono disabled:opacity-50 disabled:cursor-not-allowed"
            maxlength="6"
          />
          <p class="text-sm text-gray-500 mt-2 text-center font-medium">We've sent a verification code to your mobile</p>
        </div>
        <button
          @click="handleOTPSubmit"
          :disabled="otp.length !== 6 || isLoading || disabled"
          class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-4 px-6 rounded-xl hover:from-green-700 hover:to-emerald-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-3 font-semibold text-base shadow-lg hover:shadow-xl"
        >
          <div v-if="isLoading" class="w-6 h-6 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
          <icon v-else name="shield" class="w-6 h-6"></icon>
          Verify OTP
        </button>
      </div>
    </div>
  `,
  data() {
    return {
      mobileNumber: '',
      otp: '',
    };
  },
  methods: {
    handleMobileSubmit() {
      if (this.disabled) return;
      if (this.mobileNumber.trim()) {
        this.$emit('send-otp', this.mobileNumber);
      }
    },
    handleOTPSubmit() {
      if (this.disabled) return;
      if (this.otp.trim()) {
        this.$emit('verify-otp', this.otp);
      }
    },
    handleOTPChange(e) {
        this.otp = e.target.value.replace(/\D/g, '').slice(0, 6);
    },
  },
};