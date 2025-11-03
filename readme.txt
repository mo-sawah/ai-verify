# AI Verify WordPress Plugin

**Version:** 1.0.0  
**Author:** Mohamed Sawah  
**Website:** https://sawahsolutions.com

Professional fact-check verification tools with AI chatbot, reverse image search, and related fact-checks for WordPress.

## 📁 Plugin File Structure

Create this folder structure in your WordPress plugins directory (`wp-content/plugins/ai-verify/`):

```
ai-verify/
├── ai-verify.php                    (Main plugin file)
├── readme.txt                       (This file)
├── includes/
│   ├── settings.php                 (Settings page)
│   └── ajax-handlers.php            (AJAX handlers)
├── templates/
│   └── verification-tools.php       (Display template)
├── assets/
│   ├── css/
│   │   └── ai-verify.css           (Styles)
│   └── js/
│       └── ai-verify.js            (JavaScript)
```

## 🚀 Installation

### Step 1: Create Plugin Files

1. Go to your WordPress installation: `wp-content/plugins/`
2. Create a new folder: `ai-verify`
3. Create all the files according to the structure above
4. Copy the code from each artifact into the corresponding file

### Step 2: Activate Plugin

1. Go to WordPress Admin > Plugins
2. Find "AI Verify" in the list
3. Click "Activate"

### Step 3: Configure Settings

1. Go to Settings > AI Verify
2. Configure your API keys:
   - **OpenRouter API Key**: Sign up at https://openrouter.ai/keys
   - **Google Fact Check API Key**: Get from https://console.cloud.google.com/apis/credentials
3. Customize your CTA buttons and text
4. Enable "Auto-add to Posts" if you want automatic display

## 🔑 Getting API Keys

### OpenRouter API (for AI Chatbot)

1. Visit https://openrouter.ai
2. Sign up for an account
3. Go to "Keys" section
4. Create a new API key
5. Add credits ($5 = ~500-1000 conversations)
6. Copy the key to plugin settings

### Google Fact Check API (FREE)

1. Go to https://console.cloud.google.com
2. Create a new project (or select existing)
3. Enable "Fact Check Tools API"
4. Go to Credentials > Create Credentials > API Key
5. Copy the key to plugin settings
6. **No billing required - completely free!**

## 📖 Usage

### Method 1: Automatic Display
- Enable "Auto-add to Posts" in settings
- Tools will appear at the end of every post automatically

### Method 2: Shortcode
Use the shortcode anywhere in your posts:

```
[ai_verify]
```

**Custom Shortcode Attributes:**
```
[ai_verify show_image_search="yes" show_ai_chat="yes" show_fact_checks="yes" show_cta="yes"]
```

Set any attribute to "no" to hide that section.

## 🎨 Theme Compatibility

The plugin automatically adapts to your theme's light/dark mode using:
- `.s-light` class for light mode
- `.s-dark` class for dark mode

If your theme uses different classes, you can modify the CSS accordingly.

## ⚙️ Features

✅ **Reverse Image Search** - Quick links to Google Images, Yandex, and TinEye  
✅ **AI Chatbot** - Web-powered AI assistant using OpenRouter API  
✅ **Related Fact-Checks** - Pulls from Google's Fact Check database  
✅ **Customizable CTA** - Drive traffic to your main platform  
✅ **Dark/Light Mode** - Automatic theme adaptation  
✅ **Mobile Responsive** - Works perfectly on all devices  
✅ **Shortcode Support** - Use anywhere in your content  
✅ **Auto-add Option** - Set it and forget it

## 🔧 Troubleshooting

**AI Chatbot Not Working?**
- Check that OpenRouter API key is entered correctly
- Verify you have credits in your OpenRouter account
- Check browser console for JavaScript errors

**Fact-Checks Not Loading?**
- Verify Google Fact Check API key is correct
- Make sure "Fact Check Tools API" is enabled in Google Cloud Console
- Note: Not all topics have fact-checks available

**Styling Issues?**
- Verify your theme uses `.s-light` and `.s-dark` classes
- Check for CSS conflicts with browser inspector
- Try adding `!important` to critical styles if needed

## 💰 Costs

- **Plugin**: Free
- **Google Fact Check API**: Free (no billing required)
- **OpenRouter API**: Pay-as-you-go (~$0.01 per conversation)
  - $5 typically covers 500-1000 chatbot conversations
  - Only charged when users actually use the chatbot

## 🆘 Support

For support, visit: https://sawahsolutions.com  
Author: Mohamed Sawah

## 📝 License

GPL v2 or later

## 🔄 Changelog

### Version 1.0.0
- Initial release
- Reverse image search integration
- AI chatbot with OpenRouter
- Google Fact Check API integration
- Customizable CTA section
- Dark/light mode support
- Shortcode and auto-add functionality