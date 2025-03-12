# Flexible Popup Plugin

**Flexible Popup Plugin** is a robust and secure WordPress plugin designed to display customizable popups across your website. It comes with advanced scheduling, multilingual support, device targeting, and a variety of trigger and animation options. Perfect for websites that need to cater to multiple languages or require specific display conditions (e.g., showing on specific pages, only on mobile devices, or based on user actions).

## Features

- **Advanced Scheduling:**  
  - Configure popups to display between a start and end date/time or choose to display always.
  - Supports delayed or immediate triggers.

- **Multilingual Support:**  
  - Easily select the languages on which a popup should appear.
  - Ideal for bilingual (e.g., English and French in Canada) or multilingual websites (e.g., German, French, and Italian in Switzerland).

- **Device Targeting:**  
  - Set popups to appear on desktop, mobile, or both using built-in functions like `wp_is_mobile()`.

- **Flexible Display Conditions:**  
  - Define display rules such as specific pages (via page IDs, homepage only, or all pages).
  - Option to display a popup only once per session.

- **Rich Animations & Effects:**  
  - Choose from a variety of show/hide animations (e.g., zoom in/out, slide left/top).
  - Customize popup positioning (center, left, right, top, bottom).

- **Secure & Developer Friendly:**  
  - Implements nonce verification and capability checks to prevent CSRF and unauthorized access.
  - Uses WordPress core sanitization and escaping functions to protect against SQL injection and XSS.

## Installation

1. **Download or Clone the Repository:**  
   Clone this repository into your local machine or download the ZIP file.

2. **Upload the Plugin:**  
   Upload the entire `flexible-popup-plugin` folder to the `/wp-content/plugins/` directory of your WordPress installation.

3. **Activate the Plugin:**  
   Navigate to the **Plugins** menu in your WordPress admin dashboard and activate **Flexible Popup Plugin**.

4. **Configure Your Popups:**  
   After activation, a new admin menu item called **Flexible Popups** will appear. Use the admin interface to create, edit, and manage your popup configurations.

## Usage

- **Creating a Popup:**  
  In the admin panel, click on **Flexible Popups** > **Add New Popup**. Fill out the popup title, content, display conditions (pages, device targeting, etc.), scheduling options, language settings, and animation effects. Then click **Create Popup**.

- **Managing Popups:**  
  View all created popups in the admin list. Edit, toggle activation, or delete popups as needed. The plugin uses secure form submissions with nonces to ensure safe admin actions.

- **Front-End Display:**  
  Popups will automatically render on the front end based on the defined conditions. The plugin outputs the required HTML, CSS, and JavaScript in the footer, so no manual shortcode placement is needed.

## Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository.
2. Create your feature branch (`git checkout -b feature/YourFeature`).
3. Commit your changes (`git commit -am 'Add new feature'`).
4. Push to the branch (`git push origin feature/YourFeature`).
5. Open a pull request.

## License

This plugin is licensed under the [GPL2 License](https://www.gnu.org/licenses/gpl-2.0.html).

## Support

For any issues, questions, or suggestions, please open an issue in this repository or contact the maintainer.

---

Enjoy using **Flexible Popup Plugin** to create engaging and tailored popup experiences for your website!
