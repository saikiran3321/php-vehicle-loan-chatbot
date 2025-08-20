const Message = {
  props: ['message'],
  template: `
    <div v-if="message.content !== ''">
      <div class="flex gap-4" :class="isBot ? 'items-start' : 'items-end justify-end'">
        <div v-if="isBot" class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-lg">
          <icon name="bot" class="w-6 h-6 text-white"></icon>
        </div>
        
        <div class="max-w-sm lg:max-w-lg px-3 py-3 rounded-2xl" :class="isBot ? 'bg-white shadow-lg border border-gray-100/50 backdrop-blur-sm' : 'bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-lg'">
          <div v-if="isTyping" class="flex gap-1">
            <div class="w-2.5 h-2.5 bg-gray-400 rounded-full animate-bounce"></div>
            <div class="w-2.5 h-2.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
            <div class="w-2.5 h-2.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
          </div>
          <p v-else class="text-base leading-relaxed" :class="isBot ? 'text-gray-800' : 'text-white'">
            {{ message.content }}
          </p>
        </div>

        <div v-if="!isBot" class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-lg">
          <icon name="user" class="w-6 h-6 text-white"></icon>
        </div>
      </div>
    </div>
  `,
  computed: {
    isBot() {
      return this.message.from === 'bot';
    },
    isTyping() {
      return this.message.content === '...';
    }
  }
};