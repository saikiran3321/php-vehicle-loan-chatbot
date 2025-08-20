const ModelSelection = {
  props: ['models', 'disabled'],
  emits: ['select'],
  template: `
    <div class="bg-white rounded-2xl p-8 shadow-xl border border-gray-100/50 backdrop-blur-sm" :class="{ 'opacity-60': disabled }">
      <div class="flex items-center gap-3 mb-6">
        <icon name="settings" class="w-6 h-6 text-purple-600"></icon>
        <span class="font-semibold text-lg text-gray-800">Select Vehicle Model</span>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <button
          v-for="model in models"
          :key="model"
          @click="handleSelect(model)"
          :disabled="disabled || selectedModel !== ''"
          class="p-5 border rounded-xl transition-all transform group text-left"
          :class="getButtonClass(model)"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center transition-all" :class="getIconContainerClass(model)">
              <icon name="settings" class="w-6 h-6" :class="getIconClass(model)"></icon>
            </div>
            <span class="font-semibold text-base">{{ model }}</span>
          </div>
        </button>
      </div>
    </div>
  `,
  data() {
    return {
      selectedModel: ''
    };
  },
  methods: {
    handleSelect(model) {
      if (this.disabled) return;
      this.selectedModel = model;
      this.$emit('select', model);
    },
     getButtonClass(model) {
      if (this.selectedModel === model) {
        return 'bg-gradient-to-br from-purple-500 to-pink-600 text-white border-purple-500 shadow-lg';
      }
      if (this.selectedModel !== '' || this.disabled) {
        return 'bg-gray-100 border-gray-200 text-gray-400 cursor-not-allowed';
      }
      return 'bg-gradient-to-br from-purple-50 to-pink-50 border-purple-100 hover:from-purple-100 hover:to-pink-100 hover:border-purple-200 hover:scale-105 hover:shadow-md';
    },
    getIconContainerClass(model) {
      if (this.selectedModel === model) {
        return 'bg-white/20';
      }
      if (this.selectedModel !== '' || this.disabled) {
        return 'bg-gray-300';
      }
      return 'bg-gradient-to-br from-purple-600 to-pink-600 group-hover:from-purple-700 group-hover:to-pink-700';
    },
    getIconClass(model) {
      if (this.selectedModel === model || (this.selectedModel === '' && !this.disabled)) {
        return 'text-white';
      }
      return 'text-gray-500';
    }
  }
};