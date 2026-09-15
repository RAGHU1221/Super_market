Put these two files here before building (they're not bundled in this scaffold due to font licensing/file size):

  NotoSansTamil-Regular.ttf
  NotoSansTamil-Bold.ttf

Download from: https://fonts.google.com/noto/specimen/Noto+Sans+Tamil

Then uncomment the "fonts:" block in pubspec.yaml so Tamil text renders correctly
in both the app UI and the printed/PDF receipts (lib/utils/receipt_printer.dart
falls back to the default font if this one isn't loaded, but Tamil characters
will not display correctly without it).
