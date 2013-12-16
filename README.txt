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

You have a detailed view of this plugin possibilities in [http://www.somerandomthoughts.com/blog/2012/01/08/review-lti-provider-for-moodle-2-2/ this post by Gavin Henrik]

== Main feautres ==

Provide access to full courses or single activities.

Change the navigation block of a course or activity for displaying information and links only regarding to your current course.

Send backs course or activity final grades to the LTI consumer tool

Modify the course or activity page for hiding the header, footer and left or right blocks

== Installing and configuring ==

Follow instructions here: http://moodle.org/plugins/pluginversions.php?plugin=local_ltiprovider

'''Important''' If you are using Moodle 2.2 or above, please, be sure that this option:

 Home / > Site administration / > Security / > HTTP security Allow frame embedding

Is checked, if you leave this option unchecked your provider site will not be "embedable" via an iframe in other sites.

Once installed, a new link called "LTI Provider" will be displayed in the course navigation block .

In this page, you can add, modify and disable the tools provided in your course.

Please note that you can provide a tool n times with different configurations

There are options for hiding the page header, footer, and left and right blocks and also options for force the Moodle navigation inside a course or activity.

There are also options for assign different roles in the course or activity to the remote users.

Once added a tool, you will need to use two settings in your consumer tool:

* Shared secret

* Launch URL

Your consumer tool will ask you for a consumer private key, you can use a random string (please, do not use the shared secret as the private key)

Configure your consumer tool with these two settings. That's all

For a more detailed view of the plugin options see [http://www.somerandomthoughts.com/blog/2012/01/08/review-lti-provider-for-moodle-2-2/ this detailed review of the plugin by Gavin Henrik]

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

In order to work correctly, your php.ini settings file needs to have the following setting enabled:

allow_url_fopen = On

== Future versions ==

* Handle authentication with a custom auth plugin for Moodle (for handling logout, etc...)
* Add options for automatically add remote users to course groups.
* Add options for automatically add remote users to system cohorts.
* Add options for enabling duration time for enrolments
* Add support for consumer keys

== Credits ==

Juan Leyva <http://twitter.com/#!/jleyvadelgado>

http://moodle.org/user/profile.php?id=49568

== See also ==

[http://www.somerandomthoughts.com/blog/2012/01/08/review-lti-provider-for-moodle-2-2/ Review: LTI Provider by Gavin Henrik]

[http://moodle.org/plugins/pluginversions.php?plugin=local_ltiprovider Plugin entry]

[https://github.com/jleyva/moodle-local_ltiprovider Github page]

[[Category: Contributed code]]
