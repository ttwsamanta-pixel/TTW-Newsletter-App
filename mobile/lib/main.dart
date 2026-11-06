import 'package:flutter/material.dart';

import 'core/api.dart';
import 'core/auth.dart';
import 'widgets/ui.dart';
import 'features/auth/login.dart';
import 'features/dashboard/home.dart';

final appNavigator = GlobalKey<NavigatorState>();

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final auth = Auth(Api());
  runApp(TtwApp(auth: auth));
  auth.restore();
}

class TtwApp extends StatelessWidget {
  final Auth auth;
  const TtwApp({super.key, required this.auth});
  @override
  Widget build(BuildContext context) => MaterialApp(
    navigatorKey: appNavigator,
    title: 'TTW Newsletter Manager',
    debugShowCheckedModeBanner: false,
    theme: ThemeData(
      useMaterial3: true,
      scaffoldBackgroundColor: page,
      colorScheme: ColorScheme.fromSeed(
        seedColor: red,
        primary: red,
        secondary: navy,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: page,
        foregroundColor: navy,
        centerTitle: true,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.all(16),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: const BorderSide(color: Color(0xffdcddeb)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: const BorderSide(color: Color(0xffdcddeb)),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: red,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(13),
          ),
        ),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: const BorderSide(color: Color(0xffe7e7f1)),
        ),
      ),
    ),
    home: AnimatedBuilder(
      animation: auth,
      builder: (context, _) {
        if (auth.ready && auth.user == null) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            appNavigator.currentState?.popUntil((route) => route.isFirst);
          });
        }
        return !auth.ready
            ? const Scaffold(body: Center(child: CircularProgressIndicator()))
            : auth.user == null
            ? LoginScreen(auth: auth)
            : HomeScreen(auth: auth);
      },
    ),
  );
}
