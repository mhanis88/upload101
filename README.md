# YoPrint Laravel File Upload System

## Project Overview

This is a Laravel-based file upload system developed for YoPrint's coding interview assignment. The system handles file uploads with validation, processing, and management capabilities.

## Assignment Requirements

Based on the YoPrint Laravel Coding Project Specification, this project implements:

### Core Features
1. **File Upload System**
   - Multiple file upload support
   - File validation (type, size, etc.)
   - Secure file storage
   - File metadata management

2. **File Management**
   - File listing and browsing
   - File download functionality
   - File deletion capabilities
   - File search and filtering

3. **User Interface**
   - Clean, responsive upload interface
   - Progress indicators for uploads
   - File management dashboard
   - Error handling and user feedback

## Technical Stack

- **Framework**: Laravel 12.x
- **PHP**: ^8.2
- **Database**: SQLite (default) / MySQL
- **Frontend**: Blade Templates + Vite
- **File Storage**: Laravel Storage (local/cloud)

## Installation & Setup

### Prerequisites
- PHP 8.2+
- Composer
- Node.js & NPM

### Setup Steps
1. **Clone and Install Dependencies**
   ```bash
   composer install
   npm install
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database Setup**
   ```bash
   touch database/database.sqlite  # For SQLite
   php artisan migrate
   ```

4. **Storage Setup**
   ```bash
   php artisan storage:link
   ```

5. **Development Server**
   ```bash
   php artisan serve
   npm run dev
   ```

## File Structure

```
app/
├── Http/Controllers/
│   └── FileUploadController.php
├── Models/
│   └── FileUpload.php
├── Requests/
│   └── FileUploadRequest.php
database/
├── migrations/
│   └── create_file_uploads_table.php
resources/
├── views/
│   ├── layouts/
│   │   └── app.blade.php
│   ├── uploads/
│   │   ├── index.blade.php
│   │   └── create.blade.php
│   └── components/
│       └── file-upload.blade.php
├── js/
│   └── file-upload.js
├── css/
│   └── app.css
routes/
└── web.php
storage/
└── app/
    └── uploads/
tests/
├── Feature/
│   └── FileUploadTest.php
└── Unit/
    └── FileUploadModelTest.php
```

## Configuration

### File Upload Limits
- Maximum file size: 10MB
- Allowed file types: jpg, jpeg, png, gif, pdf, doc, docx, txt
- Maximum files per upload: 10

### Storage Configuration
- Default disk: `local`
- Upload path: `storage/app/uploads`
- Public access: Via storage link

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/uploads` | List all uploaded files |
| GET | `/uploads/create` | Show upload form |
| POST | `/uploads` | Handle file upload |
| GET | `/uploads/{id}` | Show file details |
| GET | `/uploads/{id}/download` | Download file |
| DELETE | `/uploads/{id}` | Delete file |

## Testing

Run the test suite:
```bash
php artisan test
```

Run specific tests:
```bash
php artisan test --filter FileUploadTest
```

## Development Notes

### Key Considerations
1. **Security**: Validate file types and sanitize filenames
2. **Performance**: Handle large file uploads efficiently
3. **User Experience**: Provide clear feedback and progress indicators
4. **Error Handling**: Graceful error messages and recovery
5. **Scalability**: Design for potential cloud storage integration

### Best Practices
- Use Laravel's built-in validation
- Implement proper error handling
- Follow Laravel naming conventions
- Write comprehensive tests
- Document all public methods

## Deployment

### Production Checklist
- [ ] Configure production database
- [ ] Set up file storage (S3, etc.)
- [ ] Configure environment variables
- [ ] Set up SSL certificates
- [ ] Configure web server
- [ ] Set up monitoring and logging

## Contributing

1. Follow PSR-12 coding standards
2. Write tests for new features
3. Update documentation
4. Use meaningful commit messages

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

**Assignment Status**: 🚧 In Development

**Last Updated**: $(date)

**Developer**: [Your Name]
