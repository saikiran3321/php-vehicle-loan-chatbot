const UserInfoForm = {
  props: ['isLoading', 'disabled'],
  emits: ['submit'],
  template: `
    <div class="bg-white rounded-2xl p-8 shadow-xl border border-gray-100/50 backdrop-blur-sm" :class="{ 'opacity-60': disabled }">
      <div class="flex items-center gap-3 mb-8">
        <icon name="user" class="w-6 h-6 text-green-600"></icon>
        <span class="font-semibold text-lg text-gray-800">Personal Information</span>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-base font-semibold text-gray-700 mb-3">
              <icon name="user" class="w-5 h-5 inline mr-2"></icon> Full Name
            </label>
            <input type="text" v-model="formData.name" :disabled="disabled" required class="w-full px-5 py-4 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-base disabled:opacity-50 disabled:cursor-not-allowed" placeholder="Enter your full name" />
          </div>

          <div>
            <label class="block text-base font-semibold text-gray-700 mb-3">
              <icon name="calendar" class="w-5 h-5 inline mr-2"></icon> Date of Birth
            </label>
            <input type="date" v-model="formData.dob" :disabled="disabled" required class="w-full px-5 py-4 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-base disabled:opacity-50 disabled:cursor-not-allowed" />
          </div>

          <div>
            <label class="block text-base font-semibold text-gray-700 mb-3">
              <icon name="credit-card" class="w-5 h-5 inline mr-2"></icon> PAN Number
            </label>
            <input type="text" v-model="formData.pan" :disabled="disabled" required class="w-full px-5 py-4 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-base disabled:opacity-50 disabled:cursor-not-allowed" placeholder="ABCDE1234F" maxlength="10" />
          </div>

          <div>
            <label class="block text-base font-semibold text-gray-700 mb-3">
              <icon name="dollar-sign" class="w-5 h-5 inline mr-2"></icon> Monthly Income
            </label>
            <input type="number" v-model="formData.income" :disabled="disabled" required class="w-full px-5 py-4 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-base disabled:opacity-50 disabled:cursor-not-allowed" placeholder="50000" />
          </div>
          
          <div>
            <label class="block text-base font-semibold text-gray-700 mb-3">
                <icon name="home" class="w-5 h-5 inline mr-2"></icon> Residence Type
            </label>
            <select v-model="formData.residence_type" :disabled="disabled" class="w-full px-5 py-4 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-base disabled:opacity-50 disabled:cursor-not-allowed">
                <option>Owned</option>
                <option>Rented</option>
                <option>Family Owned</option>
            </select>
          </div>
          
          <div>
            <label class="block text-base font-semibold text-gray-700 mb-3">
                <icon name="briefcase" class="w-5 h-5 inline mr-2"></icon> Employment Type
            </label>
            <select v-model="formData.employment_type" :disabled="disabled" class="w-full px-5 py-4 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-base disabled:opacity-50 disabled:cursor-not-allowed">
                <option>Salaried</option>
                <option>Self-Employed</option>
                <option>Business Owner</option>
            </select>
          </div>
        </div>

        <button type="submit" :disabled="isLoading || disabled" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-4 px-6 rounded-xl hover:from-green-700 hover:to-emerald-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-3 font-semibold text-base shadow-lg hover:shadow-xl">
          <div v-if="isLoading" class="w-6 h-6 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
          <icon v-else name="user" class="w-6 h-6"></icon>
          Submit Information
        </button>
      </form>
    </div>
  `,
  data() {
    return {
      formData: {
        name: '',
        dob: '',
        pan: '',
        income: '',
        residence_type: 'Owned',
        employment_type: 'Salaried',
        down_payment: '75000'
      }
    };
  },
  methods: {
    handleSubmit() {
      if (this.disabled) return;
      this.$emit('submit', this.formData);
    }
  }
};