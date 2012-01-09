IMS LTI PROVIDER PLUGIN FOR MOODLE

== Description ==

=== About IMS LTI ===

According IMS:

''IMS is developing Learning Tools Interoperability (LTI) to allow remote tools and content to be integrated into a Learning Management System (LMS).''

=== About this plugin ===

This is a local plugin for making Moodle a LTI provider tool.

It can be use to provide access to full courses or activities from remote systems (other Moodle installations, Sakai, any LMS LTI consumer compliant)

Please note that since Moodle 2.2 there is a core activity plugin called "External tool" that is a LTI consumer.

=== Why this plugin  ===

This plugin allow remote systems users (LTI consumers) access to Moodle courses or Moodle activities inside a course.

Moodle (version 2.2 and onwards) is a LTI consumer tool also.

You can use this plugin to share activities and courses between Moodle installations without configuring a Moodle network.

You can also share activities and courses with other LTI consumer tools like Sakai

== Main feautres ==

Provide access to full courses or single activities.

Change the navigation block of a course or activity for displaying information and links only regarding to your current course.

Send backs course or activity final grades to the LTI consumer tool

Modify the course or activity page for hiding the header, footer and left or right blocks

== Installing and configuring ==

Follow instructions here: http://moodle.org/plugins/pluginversions.php?plugin=local_ltiprovider

Once installed, a new link called "LTI Provider" will be displayed in the course navigation block .

In this page, you can add, modify and disable the tools provided in your course.

Please note that you can provide a tool n times with different configurations

There are options for hiding the page header, footer, and left and right blocks and also options for force the Moodle navigation inside a course or activity.

There are also options for assign different roles in the course or activity to the remote users.

Once added a tool, you will need to use two settings in your consumer tool:

* Shared secret

* Launch URL

Configure your consumer tool with these two settings. That's all

== How it works ==

=== User authentication ===

* Users are created automatically in their first access to the system. 
* Users are created with a hashed username and also with an auth method that disable direct login to Moodle.
* Users are allways enrolled in the course where the activities are.

You can choose which role has the Learner and the Teacher from the remote system.

There is also settings for setting Users profile default values (email visible, etc...)

If you are going to have courses with local and remote users enrolled, I recommend you to create these new roles:

* External teacher
* External student

=== Grading ===

A cron job checks periodically activities for sending back grades (overall course grade or activity grade).

== Future versions ==

* Handle authentication with a custom auth plugin for Moodle (for handling logout, etc...)
* Add options for automatically add remote users to course groups.
* Add options for automatically add remote users to system cohorts.
* Add options for enabling duration time for enrolments

== Credits ==

Juan Leyva <http://twitter.com/#!/jleyvadelgado>

http://moodle.org/user/profile.php?id=49568

== See also ==

[http://moodle.org/plugins/pluginversions.php?plugin=local_ltiprovider Plugin entry]

[https://github.com/jleyva/moodle-local_ltiprovider Github page]

[[Category: Contributed code]]
