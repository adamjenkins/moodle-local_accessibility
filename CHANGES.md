# 3.0.4

- The floating toolbar button now sits below chat and other docked panels (z-index 777, was 1050), so it no longer
  covers them. The accessibility panel still opens above the button.
- The floating toolbar button grows and shrinks with the user's text size, together with its icon and its distance
  from the bottom of the screen. Before, it kept a fixed 44px size and position while the page around it grew. At the
  default text size it looks and sits exactly as before.
