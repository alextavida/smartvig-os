import React from 'react';
import {NavigationContainer} from '@react-navigation/native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';
import {Text} from 'react-native';
import {useAuth} from '../hooks/useAuth';
import {LoginScreen} from '../screens/LoginScreen';
import {HomeScreen} from '../screens/HomeScreen';
import {OsDetailScreen} from '../screens/OsDetailScreen';
import {ProfileScreen} from '../screens/ProfileScreen';
import {CORES} from '../config';

export type RootStackParamList = {
  Login: undefined;
  App: undefined;
  OsDetail: {osId: number};
};

export type AppTabParamList = {
  Home: undefined;
  Profile: undefined;
};

const RootStack = createNativeStackNavigator<RootStackParamList>();
const AppTab = createBottomTabNavigator<AppTabParamList>();

function AppTabs() {
  return (
    <AppTab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: CORES.azul700,
        tabBarInactiveTintColor: CORES.cinza500,
        tabBarStyle: {
          backgroundColor: CORES.branco,
          borderTopColor: CORES.cinza100,
          borderTopWidth: 1,
          height: 62,
          paddingBottom: 8,
          paddingTop: 8,
        },
        tabBarLabelStyle: {fontSize: 11, fontWeight: '600'},
      }}>
      <AppTab.Screen
        name="Home"
        component={HomeScreen}
        options={{
          tabBarLabel: 'Ordens',
          tabBarIcon: ({color}) => <Text style={{fontSize: 20, color}}>📋</Text>,
        }}
      />
      <AppTab.Screen
        name="Profile"
        component={ProfileScreen}
        options={{
          tabBarLabel: 'Perfil',
          tabBarIcon: ({color}) => <Text style={{fontSize: 20, color}}>👤</Text>,
        }}
      />
    </AppTab.Navigator>
  );
}

export function Navigation() {
  const {usuario, carregando} = useAuth();

  if (carregando) {return null;}

  return (
    <NavigationContainer>
      <RootStack.Navigator screenOptions={{headerShown: false}}>
        {usuario ? (
          <>
            <RootStack.Screen name="App" component={AppTabs} />
            <RootStack.Screen
              name="OsDetail"
              component={OsDetailScreen}
              options={{
                headerShown: true,
                title: 'Ordem de Serviço',
                headerBackTitle: 'Voltar',
                headerTintColor: CORES.azul700,
                headerStyle: {backgroundColor: CORES.branco},
                headerTitleStyle: {fontWeight: '800', color: CORES.cinza900, fontSize: 16},
              }}
            />
          </>
        ) : (
          <RootStack.Screen name="Login" component={LoginScreen} />
        )}
      </RootStack.Navigator>
    </NavigationContainer>
  );
}
