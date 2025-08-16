StudentIntro is a basic web application that lets students submit answers to simple "get to know me" questions that would be helpful for a teacher or professor to know while teaching the course.

Specifically it has the following features

* A student can sign up with a name, email address, and password
* A student can fill out a short "About Me" form that includes:
    * Preferred name
    * Pronouns
    * Major or area of study
    * Academic or career goals
    * One fun fact about themselves
    * Any specific learning needs, accommodations, or preferences they'd like to share
* A student can edit their "About Me" form at any time after signing up
* A teacher can log in with their own account to view all submitted "About Me" entries for their course
* Each entry should be shown in a clean, card-style layout with the student's name and responses
* Students can only view and edit their own responses — they cannot see other students' entries
* Teachers can search or filter student responses by name or other fields
* The app supports multiple courses — a student's responses are tied to a specific course
* A teacher can generate a shareable course join code for students to connect their submissions to the right class
* Any student can join an existing course using its join code after logging in or registering
* Students who join multiple courses can fill out a separate "About Me" for each one (reusing some fields if they wish)

## Implementation details

* Email addresses are usernames; all registration and login is done with only an email and password
* Passwords are hashed but no additional security like length requirements, weak-password checks, or password confirmation is applied
* Once a user has registered, they are automatically logged in
* The login and register flow is the same — the user is registered if they don't have an account, and logged in if they do

## Technical details

* Use a single index.php script for the entire app
* SQLite for all database functionality
* No frameworks — just vanilla JavaScript and CSS
* No ORMs — use raw SQL
* Use a clean, minimalist, elegant design that is mobile-responsive