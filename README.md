Project Overview:
This project is a simple backend for managing blog posts and categories. It includes features like user authentication, blog editing, and pagination for the blog list on the homepage.

Features:
- Admin Authentication: Secure login for admins to manage blogs and categories.
- Blog Management: Create, edit, and delete blog posts.
- Category Management: Create and edit categories for organizing blog posts.
- Pagination: Display blogs with pagination on the homepage.
- Admin Role: Only authenticated users can access the blog and category management sections.

Tools & Technologies:
- **PHP**: The core language for this project.
- **MySQL**: Used as the database to store blogs and categories.
- **XAMPP**: Local development environment, which includes Apache (for running PHP) and MySQL.
- **HTML/CSS**: For the frontend.
- **Bootstrap**: For responsive design.
- **jQuery**: Used for modal functionality.


Project Structure

php-blog-backend-project/
├── /admin
│   ├── categories.php         # Manage categories
│   ├── edit-blog.php          # Blog editing
│   ├── blogs.php              # Admin blog listing
│   └── login.php              # Admin login page
├── /includes
│   ├── connect.php            # Database connection
│   ├── auth.php               # User authentication middleware
├── /assets
│   ├── /css/bootstrap.min.css # Bootstrap CSS
│   ├── /js/bootstrap.min.js   # Bootstrap JS
│   ├── /js/jquery.min.js      # jQuery for JS interactions
├── /sql
│   └── schema.sql             # SQL schema for the database
└── index.php                  # Display blogs with pagination and categories


## Setup Instructions (Using XAMPP):

### Step 1: Install XAMPP
1. If you don't already have XAMPP installed, download and install it from [here](https://www.apachefriends.org/index.html).
2. Once installed, open the **XAMPP Control Panel** and start the **Apache** and **MySQL** modules.

### Step 2: Move Project to XAMPP Directory
1. Download or clone the project.
2. Move the project folder (`project-backend`) to your XAMPP `htdocs` folder: E.g.
   - For **Windows**: `C:/xampp/htdocs/`
   - For **macOS**: `/Applications/XAMPP/htdocs/`

### Step 3: Create the Database in MySQL
1. Open **phpMyAdmin** by navigating to `http://localhost/phpmyadmin/` in your browser.
2. Create a new database called `projectname_backend`.
3. Import the SQL schema by going to the **Import** tab in `phpMyAdmin`, selecting the `schema.sql` file from the `/sql` folder, and clicking "Go".

### Step 4: Configure the Database Connection
1. Open the `includes/connect.php` file.
2. Ensure that the database credentials match your MySQL setup (default XAMPP settings shown below):
   ```php
   <?php
   // Database connection
   $servername = "localhost";
   $username = "root";         // Default username
   $password = "";             // Default password (empty by default)
   $dbname = "projctname_backend"; // Name of the database

   // Create connection
   $conn = new mysqli($servername, $username, $password, $dbname);

   // Check connection
   if ($conn->connect_error) {
       die("Connection failed: " . $conn->connect_error);
   }
   ?>


**Additional Future Features:**
If you'd like to expand the project, consider implementing the following features:

**Search Functionality:**
Add a search bar to the blog listing page that allows users to filter blog posts by title, category, or tags.

**Admin Registration**:
Build a feature that allows new admins to register on the platform. Ensure that password hashing is implemented for better security.

**User Roles:**
Extend the authentication system to include different user roles (e.g., editor, admin) with different permission levels.

**Blog Tags:**
Add a feature for categorizing blog posts using tags and allow users to search by tag.
