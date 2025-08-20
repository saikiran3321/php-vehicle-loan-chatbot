const VehicleDetails = {
  props: ['vehicle', 'disabled'],
  emits: ['select'],
  template: `
    <div class="bg-white rounded-2xl p-8 shadow-xl border border-gray-100/50 backdrop-blur-sm" :class="{ 'opacity-60': disabled }">
      <div class="flex items-center gap-3 mb-6">
        <icon name="car" class="w-6 h-6 text-blue-600"></icon>
        <span class="font-semibold text-lg text-gray-800">Select Vehicle</span>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <button
          v-for="brand in vehicle"
          :key="brand"
          @click="handleSelect(brand)"
          :disabled="disabled || selectedBrand !== ''"
          class="p-5 border rounded-xl transition-all transform group text-base font-medium"
          :class="getButtonClass(brand)"
        >
          <div class="text-center">
            <div class="w-14 h-14 rounded-2xl mx-auto mb-3 flex items-center justify-center transition-all" :class="getIconContainerClass(brand)">
              <icon name="car" class="w-7 h-7" :class="getIconClass(brand)"></icon>
            </div>
            <span>{{ brand }}</span>
          </div>
        </button>
      </div>
    </div>
  `,
  data() {
    return {
      selectedBrand: ''
    };
  },
  methods: {
    handleSelect(brand) {
      if (this.disabled) return;
      this.selectedBrand = brand;
      this.$emit('select', brand);
    },
    getButtonClass(brand) {
      if (this.selectedBrand === brand) {
        return 'bg-gradient-to-br from-blue-500 to-purple-600 text-white border-blue-500 shadow-lg';
      }
      if (this.selectedBrand !== '' || this.disabled) {
        return 'bg-gray-100 border-gray-200 text-gray-400 cursor-not-allowed';
      }
      return 'bg-gradient-to-br from-blue-50 to-purple-50 border-blue-100 hover:from-blue-100 hover:to-purple-100 hover:border-blue-200 hover:scale-105 hover:shadow-md';
    },
    getIconContainerClass(brand) {
      if (this.selectedBrand === brand) {
        return 'bg-white/20';
      }
      if (this.selectedBrand !== '' || this.disabled) {
        return 'bg-gray-300';
      }
      return 'bg-gradient-to-br from-blue-600 to-purple-600 group-hover:from-blue-700 group-hover:to-purple-700';
    },
    getIconClass(brand) {
      if (this.selectedBrand === brand || (this.selectedBrand === '' && !this.disabled)) {
        return 'text-white';
      }
      return 'text-gray-500';
    }
  }
};