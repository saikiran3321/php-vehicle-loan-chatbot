<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Vue AI Chat Assistant</title>
    <script src="./js/tailwindcss.js"></script>
    <script src="./js/vue.js"></script>
    <script src="./js/lucide.js"></script>
    <script src="./js/axios.min.js"></script>
    <link rel="stylesheet" href="./css/index.css">
    <link rel="stylesheet" href="./css/bootstrap_v5.min.css">
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              inter: ['Inter', 'sans-serif'],
            },
          }
        }
      }
    </script>
    <style>
        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1.0); }
        }
        .animate-bounce {
            animation: bounce 1.4s infinite ease-in-out both;
        }
    </style>
</head>
<body>
    <div id="app"></div>
    <script src="./components/Icon.js"></script>
    <script src="./components/Message.js"></script>
    <script src="./components/LanguageDropdown.js"></script>
    <script src="./components/PANUpload.js"></script>
    <script src="./components/OTPVerification.js"></script>
    <script src="./components/BrandSelection.js"></script>
    <script src="./components/ModelSelection.js"></script>
    <script src="./components/UserInfoForm.js"></script>
    <script src="./components/OfferCard.js"></script>
    <script src="./components/ChatInput.js"></script>
    <script src="./components/VehicleDetails.js"></script>
    <script src="./components/ChatContainer.js"></script>
    <script src="./components/App.js"></script>
    <script>
        const { createApp } = Vue;
        const app = createApp(App);
        app.component('icon', Icon);
        app.mount('#app');
    </script>
</body>
</html>