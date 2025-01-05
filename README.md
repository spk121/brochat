# half-butt-blog-engine
It is a new year.  Are you even a hacker if you aren't making another pointless blog engine?

## New Year, New Stupidity

I never made much progress on Blog Engine 2024. The theme for Blog Engine 2024 was to learn to be cloud native.  I learned a lot about cloud native: I learned that it is expensive.

Blog Engine 2025 is going back to the same theme as Blog Engine 2022, almost.  These are the goals.
1. I want it to be as easy to post on my own blog as it is to Twitter or Instagram.
   a. I should be able to take a pic from my phone straight into a blog post, like Instagram
   b. I should be able to choose a pic already saved on my phone's gallery, preview it, and make a post.
   c. I should be able to make a plain text microblog (tweet-like) post on a very simple form which is just a text box and submit button
2. I want to finally get some decent security: SSL, a proper login scheme
3. I want users to be able to engage with the content as either from HTML or Gopher
4. Even though the text of posts will live within the database, every time I write a post, I want also to archive it as a file, so I don't lose it.
5. Prefer server-side to javascript.

## Gopher?
So the HTML/Gopher thing is a bit weird, I know.

Microblog entries (tweets) will be Type I inline text.

Instgram like pic+text post will be be converted into Type G link to GIF plus Type 0 link to text.

## User Interactions
I don't want to go down the rabbit hole of dealing with user logins or comments.  I'd love to do it but with AI and such, comments are a nightmare.  So user interactions are going to be limited and not require any logins.
- HTML: a search
- HTML: anonymous Emoji-style reactions to posts
- HTML: A guestbook. Anyone can post without a login.  The publically visible guestbook will only show recent content.  Filter mercilessly: no links, no cursing.
- Gopher: A Type 6 search
- Gopher: Type g links to emojis under each post that function as like/dislike functionality
- Gopher: A Type 8 Telnet BBS for guestbook comments. Make a trivial single-screen curses based editor using forms library.

## Tech
LAMP stack, sort of
- Linux, or OpenBSD 
- nginx or lighttpd
- MySQL
- PHP
- some gopher server + PHP. Gophernicus?
- Python to handle Telnet BBS for Gopher

## Objective goals and Extra Credit
Add proper blog posts with HTML text, links, inline pictures.

For Gopher, render roper blog entries into plain text. Hyperlinks to other resources will be converted to text that says "See footnote #1" and Footnote #1 will be a link in the Gophermap.

Add ability to make video posts recorded live, with preview.  In HTML use HTML5 <video> to display them.  In Gopher, as Type I image links to an MP4 with H.264 and AAC.

Crosspost to Twitter, Instagram.

Add a streaming channel of my punk rock music archive, if I can figure out how to do it without getting flagged.
