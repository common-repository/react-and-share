(function($) {
  $(document).ready(function() {
    $('#d_reactions li').click(function() {
      var unreact = ($(this).hasClass("clicked") ? true : false);
      var id = $(this).parent().data("postId") || $(this).parent().data("post-id");
      var url = rns_data.ajax_url;
      var reaction = $(this).data().reaction;
      $.post(url, { postid: id, action: 'rns_react', reaction: reaction, unreact: unreact }, function(data) {
        console.log("Ajax: " + data);
      });

      $(this).toggleClass("clicked");

      var howMany = parseInt($(this).find('span').text());
      if (howMany > 0) {
        if ($(this).hasClass("clicked")) {
          howMany += 1;
        } else {
          howMany -= 1;
        }
      } else {
        howMany = 1;
      }
      $(this).find('span').text(howMany);

      $(this).closest("#d_reactions").find("#d_reactions_shares").addClass("showshares");
    });

    $('.rns-fb-share').click(function(e) {
      e.preventDefault();
      window.open($(this).attr('href'), 'fbShareWindow', 'height=450, width=550, top=' + ($(window).height() / 2 - 275) + ', left=' + ($(window).width() / 2 - 225) + ', toolbar=0, location=0, menubar=0, directories=0, scrollbars=0');
      return false;
    });

    $('.rns-twitter-share').click(function(e) {
      e.preventDefault();
      window.open($(this).attr('href'), 'twitterShareWindow', 'height=450, width=550, top=' + ($(window).height() / 2 - 275) + ', left=' + ($(window).width() / 2 - 225) + ', toolbar=0, location=0, menubar=0, directories=0, scrollbars=0');
      return false;
    });

    $('.rns-whatsapp-share').click(function(e) {
      e.preventDefault();
      var reactions = $(this).closest("#d_reactions").find(".clicked");
      var length = reactions.length;
      var emoticons = {
        like: "👍",
        happy: "😂",
        love: "😍",
        sad: "😭",
        surprised: "😮",
        angry: "😡"
      };

      var sharedText = "";
      for (var i = 0; i < length; i++) {
        var reaction = $(reactions[i]).data("reaction");
        sharedText += emoticons[reaction];
      }
      var postUrl = $(this).attr('href');
      sharedText += " " + postUrl;

      var shareUrl = "whatsapp://send?text=" + encodeURIComponent(sharedText);
      window.location.href = shareUrl;
      return false;
    });
  });

})(jQuery);

document.addEventListener("touchstart", function() {}, true);

if ('createTouch' in document) {
  try {
    var ignore = /:hover/;
    for (var i = 0; i < document.styleSheets.length; i++) {
      var sheet = document.styleSheets[i];
      for (var j = sheet.cssRules.length - 1; j >= 0; j--) {
        var rule = sheet.cssRules[j];
        if (rule.type === CSSRule.STYLE_RULE && ignore.test(rule.selectorText)) {
          sheet.deleteRule(j);
        }
      }
    }
  } catch (e) {}
}
