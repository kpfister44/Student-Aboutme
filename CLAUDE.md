# StudentIntro Project - Claude Code Context

## Project Overview
StudentIntro is a PHP web application for student "About Me" submissions that teachers can review. Built as a single-page application with vanilla PHP, JavaScript, and CSS.

## Key Technical Requirements
- Single `index.php` file for entire application
- SQLite database (no ORM, raw SQL only)
- Vanilla JavaScript and CSS (no frameworks)
- Mobile-responsive, clean, minimalist design
- Email-based authentication with password hashing

## Database Schema
The application needs tables for:
- `users` (id, email, password_hash, name, role, created_at)
- `courses` (id, name, join_code, teacher_id, created_at)
- `course_enrollments` (id, user_id, course_id, created_at)
- `student_profiles` (id, user_id, course_id, preferred_name, pronouns, major, goals, fun_fact, learning_needs, updated_at)

## Core Features
1. **Authentication**: Email/password login/register (auto-login after register)
2. **Student Features**: Fill/edit "About Me" forms per course, join courses via code
3. **Teacher Features**: View all student profiles in their courses, generate join codes, search/filter
4. **Multi-course Support**: Students can have different profiles per course

## Development Commands

### Starting the Application
```bash
# Navigate to project directory
cd /Users/kyle.pfister/Student-Aboutme

# Start the development server
php -S localhost:8000

# Open in browser
open http://localhost:8000
```

### Additional Notes
- Database initialization handled automatically in application code
- No build process required (vanilla stack)
- SQLite database file (`database.db`) created automatically on first run

## File Structure
```
/
├── index.php (main application file)
├── database.db (SQLite database, auto-created)
├── SPEC.md (project specification)
└── CLAUDE.md (this file)
```

## Security Notes
- Passwords hashed with PHP's `password_hash()`
- No additional password requirements per spec
- Session-based authentication
- Role-based access control (student/teacher)

## Code Documentation Guidelines

### **Comment Philosophy**
Documentation should capture **design decisions and intentions** at the time of creation, not just functionality. Comments should explain the "why" and "when," not just the "what."

### **Good vs Poor Comments**

**❌ Poor (describes functionality):**
```python
# This function splits the input data into two equally sized chunks, 
# multiplies each chunk with Y and then adds it together
def process_chunks(data, multiplier):
```

**✅ Good (explains design decision):**
```python
# The hardware X that this code runs on has a cache size of Y which 
# makes this split necessary for optimal compute throughput
def process_chunks(data, multiplier):
```

### **Three Types of Documentation (Airplane Metaphor)**
1. **800-page manual** - Comprehensive but overwhelming ("Congratulations on purchasing your 747!")
2. **10-page guide** - Practical how-to ("How to change the oil in the engine")  
3. **5-item checklist** - Critical emergency procedures ("How to deal with a fire in the engine")

**Use type 2 and 3 for code comments** - focus on practical understanding and critical design decisions.

### **What to Document**
- **Hardware constraints** that influenced implementation choices
- **Performance considerations** that drove specific algorithms
- **Historical context** for unusual patterns ("This works around API limitation X")
- **Future considerations** ("When new hardware Y is available, consider Z approach")
- **Critical failure modes** and why specific safeguards exist

### **What NOT to Document**
- Obvious functionality that code already expresses
- Implementation details that are self-evident
- Redundant descriptions of what the code does

Remember: Future engineers need to understand **why** code exists in its current form to make informed decisions about changes.

## Commit Message Guidelines

Keep commit messages concise and to the point:

- Use imperative mood: "Add user authentication" not "Added user authentication"
- First line under 50 characters, capitalize first word
- Focus on what the change accomplishes, not how
- Use present tense: "Fix login bug" not "Fixed login bug"

**Examples:**
- `Add student profile creation form`
- `Fix course join code validation`
- `Update database schema for multi-course support`
- `Refactor authentication middleware`

