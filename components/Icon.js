const Icon = {
  props: {
    name: {
      type: String,
      required: true,
    },
    iconClass: {
      type: String,
      default: '',
    },
  },
  template: `<i :data-lucide="name" :class="iconClass"></i>`,
  
  mounted() {
    this.$nextTick(() => {
      if (this.$el.nodeName === 'I' && typeof lucide !== 'undefined') {
        lucide.createIcons({
          nodes: [this.$el],
        });
      }
    });
  },
};