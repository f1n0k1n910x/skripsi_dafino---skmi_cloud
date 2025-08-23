# SKMI Cloud Storage - Huda Version

This is a refactored and modernized version of the SKMI Cloud Storage application, organized in a clean, modular structure for better maintainability and scalability.

## 🚀 Features

- **Modern UI/UX**: Clean, responsive design with Metro-style interface
- **Modular Architecture**: Well-organized code structure with separation of concerns
- **Responsive Design**: Works seamlessly on desktop, tablet, and mobile devices
- **Advanced Search**: Real-time file search with filters and highlighting
- **Drag & Drop Upload**: Intuitive file upload with progress tracking
- **File Management**: Complete file and folder management capabilities
- **Storage Monitoring**: Real-time storage usage tracking
- **Cross-browser Support**: Works on all modern browsers

## 📁 Project Structure

```
huda/
├── index.php                 # Main entry point
├── includes/
│   └── init.php             # Application initialization and routing
├── templates/
│   ├── header.php           # Common header template
│   └── footer.php           # Common footer template
├── pages/
│   ├── dashboard.php        # Dashboard page
│   ├── files.php            # Files management page
│   ├── recycle_bin.php      # Recycle bin page
│   ├── profile.php          # User profile page
│   └── members.php          # Members management page
├── assets/
│   ├── css/
│   │   ├── main.css         # Main stylesheet
│   │   └── responsive.css   # Responsive design styles
│   └── js/
│       ├── main.js          # Core application functionality
│       ├── search.js        # Search functionality
│       └── upload.js        # File upload handling
└── README.md                # This file
```

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Database**: MySQL/MariaDB
- **Icons**: Font Awesome 6
- **Styling**: CSS Grid, Flexbox, CSS Variables
- **Responsiveness**: Mobile-first approach with CSS Media Queries

## 🚀 Getting Started

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

### Installation

1. **Clone or download** the project to your web server directory
2. **Configure database** connection in `../config.php`
3. **Set permissions** for uploads directory
4. **Access the application** via `huda/index.php`

### Configuration

The application uses the existing configuration from the parent directory:
- Database connection settings in `../config.php`
- User authentication system
- File storage paths

## 📱 Usage

### Navigation

- **Dashboard**: Overview of storage usage and recent files
- **Files**: Browse and manage your files and folders
- **Recycle Bin**: Restore or permanently delete files
- **Profile**: Manage your account settings
- **Members**: Manage team members (if applicable)

### File Operations

- **Upload**: Drag & drop files or use the upload button
- **Download**: Click download button on any file
- **Search**: Use the search bar to find files quickly
- **Organize**: Create folders and move files between them
- **Share**: Generate share links for files

### Keyboard Shortcuts

- `Ctrl/Cmd + K`: Focus search bar
- `Ctrl/Cmd + U`: Open upload modal
- `Escape`: Close modals and clear search

## 🎨 Customization

### Styling

The application uses CSS variables for easy customization:

```css
:root {
    --primary-color: #0078D7;      /* Main brand color */
    --secondary-color: #2D2D30;    /* Sidebar background */
    --accent-color: #4CAF50;       /* Success/accent color */
    --text-primary: #333333;       /* Primary text color */
    --bg-primary: #FFFFFF;         /* Main background */
}
```

### Adding New Pages

1. Create a new PHP file in the `pages/` directory
2. Add the route in `includes/init.php`
3. Create corresponding navigation link in `templates/header.php`

### Extending Functionality

The modular structure makes it easy to add new features:
- JavaScript classes for new functionality
- CSS modules for new components
- PHP classes for backend logic

## 🔧 Development

### Code Style

- **PHP**: PSR-12 coding standards
- **JavaScript**: ES6+ with modern syntax
- **CSS**: BEM methodology for class naming
- **HTML**: Semantic HTML5 structure

### Browser Support

- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

### Performance

- Lazy loading for images
- Debounced search input
- Optimized CSS and JavaScript
- Minimal DOM manipulation

## 🐛 Troubleshooting

### Common Issues

1. **Upload not working**: Check file permissions and PHP upload limits
2. **Search not functioning**: Verify database connection and table structure
3. **Styling issues**: Ensure all CSS files are loaded correctly
4. **JavaScript errors**: Check browser console for error messages

### Debug Mode

Enable debug mode by setting error reporting in PHP:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## 📈 Future Enhancements

- [ ] Real-time notifications
- [ ] Advanced file preview
- [ ] Collaborative editing
- [ ] API endpoints for mobile apps
- [ ] Advanced analytics dashboard
- [ ] Multi-language support
- [ ] Dark/light theme toggle
- [ ] Offline functionality

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is part of the SKMI Cloud Storage system. Please refer to the main project license.

## 📞 Support

For support and questions:
- Check the documentation
- Review existing issues
- Create a new issue with detailed information

---

**Note**: This is a refactored version of the original SKMI Cloud Storage application. It maintains compatibility with the existing database structure while providing a modern, maintainable codebase.
