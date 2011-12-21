IMS LTI PROVIDER PLUGIN FOR MOODLE

== Description ==

This is a local plugin for making Moodle a LTI provider tool.

It can be use to provide access to full courses or activities from remote systems (other Moodle installations, Sakai, any LMS LTI consumer compliant)


== Main feautres ==

Provide access to full courses or single activities.

Change the navigation block of a course or activity for displaying information and links only regarding to your current course or activity.

Send backs course or activity final grades to the LTI consumer tool


== How it works ==

=== User authentication ===

Users are created automatically in their first access to the system. 
Users are created with a hashed username and also with an auth method that disable direct login to Moodle.
Users are allways enrolled in the course where the activities are.
You can choose which role has the Learner and the Teacher from the remote system.
There is also settings for setting Users profile default values (email visible, etc...)

If you are going to have courses with local and remote users enrolled, I recommend you to create these new roles:
External teacher
External student 


=== Grading ===

A cron job checks periodically activities for sending back grades.

== Future versions ==

Handle authentication with a custom auth plugin for Moodle (for handling logout, etc...)
Add options for automatically add remote users to course groups.
Add options for automatically add remote users to system cohorts.
Add options for enabling duration time for enrolments