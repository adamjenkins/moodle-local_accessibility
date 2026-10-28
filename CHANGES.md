# 3.0.6

- Text with a fixed size set in the editor (for example 8pt from TinyMCE or tiny_fontsize, or a pixel size in pasted
  content) now grows and shrinks with the chosen text size, and so does a fixed line height. Before, only text without
  its own size followed the setting (GitHub issue #2).
- In authored text, a box with a fixed height grows with its text instead of cutting it off. Images, media and
  embedded frames keep their size.
- Colour schemes now also replace highlight backgrounds written as `background:`, old `<font color>` and table
  `bgcolor` colours, and colours in authored text outside the main column, such as HTML blocks. Before, these could
  leave light text on a pale highlight or dark text on a dark page.
