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

== Plugin version 2.4 and above features ==

=== The plugin settings link is displayed in the settings block, instead the course one ===

=== Several new settings for control different features of the plugin: ===
- How the user profile is updated
- Default authentication method
- Format of the course shortname, fullname and idnumber (using LTI variables)
- Roles allowed to create new contexts
- Roles allowed to create new resources

=== The remote tool can be opened using the context_id ===

The tool can be opened using also the context_id instead the current internal Moodle id

=== Support for context memberships service ===

See http://developers.imsglobal.org/ext_membership.html

=== LTI custom parameters to force settings on SSO ===

See https://tracker.moodle.org/browse/CONTRIB-4502

=== Service for context (course) creation, using other courses as template ===

Service URL local/ltiprovider/services.php

Custom parameters:

custom_service = create_context

custom_context_template = Moodle idnumber for a course to be used as a template (the course will be duplicate)

The course will be created populating the fullname, shortname and idnumber configured in the plugin settings

=== Service for resources duplication ===

Service URL local/ltiprovider/services.php

Custom parameters:

custom_service = duplicate_resource

custom_resource_link_copy_id = Moodle idnumber of the activity to be duplicated in the current context

=== SSO to resources ===

If the context resource_link_id matches to an activity idnumber, the user will be redirect to that activity in Moodle

=== Automatic creation of resources (moodle activities) ===

If this additional parameter is present in the request custom_resource_link_type (mod_forum, etc...)  and also resource_link_title and resource_link_description a new moodle activity will be created

See: https://tracker.moodle.org/browse/CONTRIB-4409

=== Automatic creation of contexts on SSO ===

Two additional request parameters are required:

custom_create_context (0 or 1)

custom_context_template (Moodle course idnumber)


=== Resources duplication on SSO ==

Additional parameter required:

custom_resource_link_copy_id = Moodle idnumber of the activity to be duplicated in the current context


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


== Credits ==

Juan Leyva <http://twitter.com/#!/jleyvadelgado>

http://moodle.org/user/profile.php?id=49568

The Universitat Oberta de Catalunya (UOC) <http://www.uoc.edu/> has sponsored the version 2.3 of this plugin

== See also ==

[http://www.somerandomthoughts.com/blog/2012/01/08/review-lti-provider-for-moodle-2-2/ Review: LTI Provider by Gavin Henrik]

[http://moodle.org/plugins/pluginversions.php?plugin=local_ltiprovider Plugin entry]

[https://github.com/jleyva/moodle-local_ltiprovider Github page]

[[Category: Contributed code]]
