texts
=====

Some stuff for routine work with text

Examples
--------

    // Create announce
    $title   = "I am title";
    $content = "I am content.";
    $announce = \Texts\Common::annotate($title, $content);

    // Convert notation
    $camel = "iAmCamelCasedString";
    $under = \Texts\Common::underscorize($camel);
    $camel = \Texts\Common::camelize($under);