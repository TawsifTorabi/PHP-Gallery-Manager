
# PHP Gallery Manager 🖼️📹

A feature-rich, high-performance PHP CRUD application designed to manage image and video galleries. This project focuses on handling large media files efficiently, supporting modern image formats, and providing advanced tools for media extraction and session security.

## 🚀 Key Features

### 📦 Media Management

-   **Efficient CRUD:** Full Create, Read, Update, and Delete operations for galleries and individual media items.
    
-   **Chunked Upload System:** Supports extremely large files (tested up to 1.7GB+) by breaking uploads into smaller binary chunks to bypass server `upload_max_filesize` limits.
    
-   **Modern Format Support:** Native handling of **HEIC** (iPhone), **WebP**, and standard formats. Automatic high-quality conversion to JPG using ImageMagick for cross-browser compatibility.
    

### 🎥 Video Intelligence

-   **Frame Seeker & Extractor:** Precise frame-by-frame navigation to capture high-resolution stills from video files.
    
-   **Auto-Suggestions:** Automatically generates 10 random frame suggestions from uploaded videos to jumpstart gallery creation.
    
-   **Background Compression:** Integration hooks for FFmpeg to handle video compression in the background without blocking the UI.
    

### 🔐 Security & UX

-   **Inactivity Session Locker:** A built-in security module that blurs the application and requires a PIN after a period of user inactivity.
    
-   **Persistent State:** Security locks persist across page refreshes using `localStorage`.
    
-   **Dark Mode:** A fully integrated dark mode toggle that adapts all UI components, including the security overlays.
    

## 🛠️ Tech Stack

-   **Backend:** PHP (MySQLi, FFmpeg, ImageMagick)
    
-   **Frontend:** JavaScript (ES6 Modules), Bootstrap 5, CSS3
    
-   **Database:** MySQL
    

## 📋 Installation

1.  **Clone the repository:**
    
    Bash
    
    ```
    git clone https://github.com/TawsifTorabi/PHP-Gallery-Manager.git
    
    ```
    
2.  **Database Setup:**
    
    -   Import the provided SQL schema (or run the `ALTER TABLE` queries for the users' session settings).
        
    -   Configure your credentials in `db.php`.
        
3.  **Server Requirements:**
    
    -   PHP 7.4+
        
    -   `exec()` enabled (for FFmpeg and ImageMagick).
        
    -   `libheif` installed (for HEIC support).
        
4.  **Directory Permissions:** Ensure the `uploads/` and `temp_chunks/` folders are writable:
    
    Bash
    
    ```
    chmod -R 777 uploads/ temp_chunks/
    
    ```
## Run with Docker

This project is fully Dockerized, containing all necessary system dependencies including FFmpeg and ImageMagick.

1.  **Build and start the containers:** `docker-compose up -d --build`
    
2.  **Access the application:** Open your browser and navigate to `http://localhost:8080`
    
3.  **Stop the environment:** `docker-compose down`
    

## Tech Stack

-   **Backend:** PHP 7.4+ (MySQLi)
    
-   **Frontend:** Vanilla JavaScript (ES6 Modules), Bootstrap 5
    
-   **Utilities:** FFmpeg (Video Processing), ImageMagick (Image Conversion)
    
-   **Database:** MySQL
    

## Installation & Database Setup

1.  **Clone the repository:** `git clone https://github.com/TawsifTorabi/PHP-Gallery-Manager.git`
    
2.  **Prepare the Database:** Ensure your `users` table includes the following columns to support the security module:
    
    -   `timeout_enabled` (BOOLEAN)
        
    -   `user_timeout_preference` (INT - seconds)
        
    -   `unlock_pin` (VARCHAR)
        
3.  **Permissions:** Ensure the `uploads/` and `temp_chunks/` directories are writable by the server.
    

## 🖥️ Usage

### Session Security Configuration

To enable the inactivity locker, ensure your `users` table has the following columns:

SQL

```
ALTER TABLE users 
ADD COLUMN timeout_enabled BOOLEAN DEFAULT 1,
ADD COLUMN user_timeout_preference INT DEFAULT 300,
ADD COLUMN unlock_pin VARCHAR(255) DEFAULT '1234';

```

### Video Frame Extraction

When viewing a video in the "Update Gallery" view, use the **Frame Seeker** buttons to find the perfect shot. Click **Capture** to add the frame to your gallery instantly.

## 🤝 Contributing

Contributions are welcome! If you have a feature request or find a bug, please open an issue or submit a pull request.

## 📜 License

This project is licensed under the MIT License - see the [LICENSE](https://www.google.com/search?q=LICENSE&authuser=1) file for details.

----------

_Developed with ❤️ by [Tawsif Torabi](https://github.com/TawsifTorabi)_