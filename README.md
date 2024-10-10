# php-blog-project-backend
 
project-backend/
├── /admin
│   ├── categories.php         # Manage categories
│   ├── edit-blog.php          # Blog editing
│   ├── list-blogs.php              # Admin blog listing
│   └── login.php              # Admin login page
├── /includes
│   ├── connect.php            # Database connection
│   ├── auth.php               # User authentication middleware
├── /assets
│   ├── /css/bootstrap.min.css # Bootstrap CSS
│   ├── /js/bootstrap.min.js   # Bootstrap JS
│   ├── /js/jquery.min.js      # jQuery for JS interactions
├── /sql
│   └── ruru_schema.sql             # SQL schema for the database
└── index.php                  # Display blogs with pagination and categories



Task 1: Fix Category Description Modal (categories.php)
Objective: On the admin categories page, fix the modal so that it displays the correct category description from the database.

Instructions:
1. Create a categories.php file inside the /admin folder.
2. Query the database to fetch categories and their descriptions.
3. Add a modal that shows the description when the user clicks the "View Description" button.

Task 2: Enable Blog Editing and Saving (edit-blog.php)
Objective: Create a form to edit blog details and save the changes in the database.

Instructions:
1. Create an edit-blog.php file in /admin.
2. Fetch blog details for the blog to be edited.
3. Validate and update the blog in the database when the form is submitted.

Task 3: Display Blogs with Categories (index.php)
Objective: Display blogs along with their corresponding categories on the homepage.

Instructions:
1. Create an index.php file in the root directory.
2. Fetch blogs and their categories from the database and display them.

Step 4: SQL Schema to script
1. Create a categories table with the columns id, name, and description.
2. Create a blogs table with the columns id, title, content, category_id, tags, and author_name. 
N.B. The category_id is a foreign key referencing the id in the categories table to maintain the relationship between blogs and their categories.