const LanguageDropdown = {
  props: {
    disabled: {
      type: Boolean,
      default: false
    }
  },
  emits: ['select'],
  template: `
    <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-100/50 backdrop-blur-sm" :class="{ 'opacity-60': disabled }">
      <div class="flex items-center gap-3 mb-4">
        <icon name="globe" class="w-6 h-6 text-blue-600"></icon>
        <span class="font-semibold text-lg text-gray-800">Select Language</span>
      </div>
      
      <div class="relative">
        <button
          @click="toggleDropdown"
          :disabled="disabled"
          class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-xl flex items-center justify-between transition-all text-base"
          :class="{ 'cursor-not-allowed': disabled, 'hover:bg-gray-100 hover:border-gray-300 hover:shadow-md': !disabled }"
        >
          <span class="font-medium" :class="selected ? 'text-gray-800' : 'text-gray-500'">
            {{ selected || 'Choose your preferred language' }}
          </span>
          <icon name="chevron-down" class="w-6 h-6 text-gray-400 transform transition-transform" :class="{ 'rotate-180': isOpen }"></icon>
        </button>

        <div v-if="isOpen && !disabled" class="absolute top-full left-0 right-0 mt-2 bg-white border border-gray-200 rounded-xl shadow-xl z-10 backdrop-blur-sm">
          <button
            v-for="language in languages"
            :key="language"
            @click="handleSelect(language)"
            class="w-full px-5 py-4 text-left hover:bg-blue-50 transition-colors first:rounded-t-xl last:rounded-b-xl font-medium text-base"
          >
            {{ language }}
          </button>
        </div>
      </div>
    </div>
  `,
  data() {
    return {
      isOpen: false,
      selected: '',
      languages: ['English', 'Hindi', 'Telugu', 'Tamil', 'Bengali']
    };
  },
  methods: {
    toggleDropdown() {
        if (!this.disabled) {
            this.isOpen = !this.isOpen;
        }
    },
    handleSelect(language) {
      if (this.disabled) return;
      this.selected = language;
      this.isOpen = false;
      this.$emit('select', language);
    }
  }
};