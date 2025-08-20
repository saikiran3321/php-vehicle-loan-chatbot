const OfferCard = {
  props: ['offer'],
  template: `
    <div class="rounded-2xl p-8 border-2 transition-all hover:shadow-xl hover:scale-[1.02] backdrop-blur-sm" :class="statusColor">
      <div class="flex items-start justify-between mb-6">
        <div class="flex items-center gap-4">
          <div class="w-16 h-16 bg-gradient-to-br from-blue-600 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg">
            <icon name="building-2" class="w-8 h-8 text-white"></icon>
          </div>
          <div>
            <h3 class="font-bold text-xl text-gray-800">{{ offer.bankName }}</h3>
            <div class="flex items-center gap-2 mt-1">
              <icon :name="statusIcon" class="w-5 h-5" :class="statusIconColor"></icon>
              <span class="text-base font-semibold text-gray-600">{{ offer.status }}</span>
            </div>
          </div>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-6">
        <div class="flex items-center gap-3">
          <icon name="trending-down" class="w-6 h-6 text-blue-600"></icon>
          <div>
            <p class="text-sm text-gray-500 font-medium">Interest Rate</p>
            <p class="font-bold text-xl text-gray-800">{{ offer.interestRate }}%</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <icon name="dollar-sign" class="w-6 h-6 text-green-600"></icon>
          <div>
            <p class="text-sm text-gray-500 font-medium">Loan Amount</p>
            <p class="font-bold text-xl text-gray-800">₹{{ offer.amount.toLocaleString() }}</p>
          </div>
        </div>
      </div>
      <button class="w-full mt-6 bg-gradient-to-r from-blue-600 to-purple-600 text-white py-4 px-6 rounded-xl hover:from-blue-700 hover:to-purple-700 transition-all font-semibold text-base shadow-lg hover:shadow-xl">
        Apply Now
      </button>
    </div>
  `,
  computed: {
    statusDetails() {
        switch (this.offer.status.toLowerCase()) {
            case 'pre-approved':
            case 'approved':
                return { icon: 'check-circle', color: 'text-green-500', bgColor: 'bg-green-50 border-green-200' };
            case 'under review':
                return { icon: 'clock', color: 'text-yellow-500', bgColor: 'bg-yellow-50 border-yellow-200' };
            default:
                return { icon: 'alert-circle', color: 'text-blue-500', bgColor: 'bg-blue-50 border-blue-200' };
        }
    },
    statusIcon() {
        return this.statusDetails.icon;
    },
    statusIconColor() {
        return this.statusDetails.color;
    },
    statusColor() {
        return this.statusDetails.bgColor;
    }
  }
};