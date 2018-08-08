The manual grading by student quiz report
https://moodle.org/plugins/quiz_gradingstudents

This 'report' is actually a tool like the standard Manual grading quiz report,
but which lets you grade all the responses by one student, rather than all
the responses to one question.

This version of the plugin, which is compatible with Moodle 3.4+ and later.
Older versions of this plugin will work with older Moodle versions.

You can install it from the Moodle plugins database using the link above.

Alternatively, you can install it using git. In the top-level folder of your
Moodle install, type the command:
    git clone git://github.com/moodleou/moodle-quiz_gradingstudents.git mod/quiz/report/gradingstudents
    echo '/mod/quiz/report/gradingstudents/' >> .git/info/exclude

Then visit the admin screen to allow the install to complete.

Once the plugin is installed, you can access the functionality by going to
Reports -> Manual grading by student in the Quiz adminstration block.

To avoid confusion, you may wish to use Moodle's
http://docs.moodle.org/en/Language_customization
to rename the standard 'Manual grading' report to 'Manual grading by question'.
The string you are looking for is in the 'quiz_grading.php' component. You need
to edit the 'grades' string.
