const PANUpload = {
  props: {
    isLoading: Boolean,
    disabled: Boolean
  },
  emits: ['submit'],
  template: `
    <div class="bg-white rounded-2xl p-8 shadow-xl border border-gray-100/50 backdrop-blur-sm" :class="{ 'opacity-60': disabled }">
      <div class="flex items-center gap-3 mb-6">
        <icon name="file-text" class="w-6 h-6 text-blue-600"></icon>
        <span class="font-semibold text-lg text-gray-800">PAN Card Details</span>
      </div>

      <div class="space-y-6">
        <form class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-base font-semibold text-gray-700 mb-3">
              <icon name="user" class="w-5 h-5 inline mr-2"></icon> Full Name
            </label>
            <input type="text" v-model="userInput.name" :disabled="disabled" required class="w-full px-5 py-4 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-base disabled:opacity-50 disabled:cursor-not-allowed" placeholder="Enter your full name" />
          </div>
          <div>
            <label class="block text-base font-semibold text-gray-700 mb-3">
              <icon name="calendar" class="w-5 h-5 inline mr-2"></icon> Date of Birth
            </label>
            <input type="date" v-model="userInput.dob" :disabled="disabled" required class="w-full px-5 py-4 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-base disabled:opacity-50 disabled:cursor-not-allowed" />
          </div>
          <div>
            <label class="block text-base font-semibold text-gray-700 mb-3">
              <icon name="credit-card" class="w-5 h-5 inline mr-2"></icon> PAN Number
            </label>
            <input type="text" v-model="userInput.pan" :disabled="disabled" required class="w-full px-5 py-4 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-base disabled:opacity-50 disabled:cursor-not-allowed" placeholder="ABCDE1234F" maxlength="10" />
          </div>
        </div>
      </form>
        
        <div
          @drop.prevent="handleDrop"
          @dragover.prevent="handleDragOver"
          @dragleave="dragOver = false"
          class="border-2 border-dashed rounded-xl p-8 text-center transition-colors d-none"
          :class="[
            dragOver ? 'border-blue-400 bg-blue-50' : 'border-gray-300 hover:border-gray-400',
            disabled ? 'opacity-50 cursor-not-allowed' : ''
          ]"
        >
          <icon name="upload" class="w-10 h-10 text-gray-400 mx-auto mb-3"></icon>
          <p class="text-base text-gray-600 mb-3 font-medium">
            Drop your PAN card here or click to browse
          </p>
          <input
            type="file"
            @change="handleFileChange"
            :disabled="disabled"
            accept="image/*,.pdf"
            class="hidden"
            id="pan-upload"
            ref="fileInput"
          />
          <label
            for="pan-upload"
            class="inline-block px-6 py-3 bg-blue-100 text-blue-700 rounded-xl font-semibold transition-colors"
            :class="disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer hover:bg-blue-200 hover:shadow-md'"
          >
            Choose File
          </label>
          <p v-if="file" class="text-base text-green-600 mt-3 font-medium">
            Selected: {{ file.name }}
          </p>
        </div>

        <button type="submit" @click="handleSubmit" :disabled="isLoading || disabled" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-4 px-6 rounded-xl hover:from-green-700 hover:to-emerald-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-3 font-semibold text-base shadow-lg hover:shadow-xl">
          <div v-if="isLoading" class="w-6 h-6 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
          <icon v-else name="user" class="w-6 h-6"></icon>
          Submit Details
        </button>
      </div>
    </div>
  `,
  data() {
    return {
      userInput: {
        name: '',
        dob: '',
        pan: '',
      },
      file: null,
      dragOver: false
    };
  },
  methods: {
    handleFileChange(e) {
      if (this.disabled) return;
      if (e.target.files && e.target.files[0]) {
        this.file = e.target.files[0];
      }
    },
    handleDrop(e) {
      if (this.disabled) return;
      this.dragOver = false;
      if (e.dataTransfer.files && e.dataTransfer.files[0]) {
        this.file = e.dataTransfer.files[0];
        this.$refs.fileInput.files = e.dataTransfer.files;
      }
    },
    handleDragOver() {
      if (!this.disabled) {
        this.dragOver = true;
      }
    },
    handleSubmit() {
      if (this.disabled) return;
      this.$emit('submit', this.userInput || 'PAN uploaded', this.file);
      this.userInput = '';
      this.file = null;
    }
  }
};