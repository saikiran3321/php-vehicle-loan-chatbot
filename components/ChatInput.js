const ChatInput = {
  emits: ['send'],
  template: `
    <form @submit.prevent="handleSubmit" class="d-flex align-items-center justify-content-between gap-3">
      <input
        type="text"
        v-model="message"
        placeholder="Type your message..."
        class="form-control form-control-lg"
      />
      <button
        type="submit"
        :disabled="!message.trim()"
        class="btn btn-lg btn-outline-primary"
      >
        send
      </button>
    </form>
  `,
  data() {
    return {
      message: ''
    };
  },
  methods: {
    handleSubmit() {
      if (this.message.trim()) {
        this.$emit('send', this.message);
        this.message = '';
      }
    }
  }
};