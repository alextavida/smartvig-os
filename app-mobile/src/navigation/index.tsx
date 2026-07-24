import React from 'react';
import {NavigationContainer} from '@react-navigation/native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';
import {Text, View} from 'react-native';
import {useAuth} from '../hooks/useAuth';
import {LoginScreen} from '../screens/LoginScreen';
import {HomeScreen} from '../screens/HomeScreen';
import {OsDetailScreen} from '../screens/OsDetailScreen';
import {ProfileScreen} from '../screens/ProfileScreen';
import {CreateOsScreen} from '../screens/CreateOsScreen';
import {ProdutividadeScreen} from '../screens/ProdutividadeScreen';
import {useOfflineQueue} from '../hooks/useOfflineQueue';
import {CORES} from '../config';

export type RootStackParamList = {
  Login: undefined;
  App: undefined;
  OsDetail: {osId: number};
  CreateOs: undefined;
};

export type AppTabParamList = {
  Home: undefined;
  Produtividade: undefined;
  Profile: undefined;
};

const RootStack = createNativeStackNavigator<RootStackParamList>();
const AppTab   = createBottomTabNavigator<AppTabParamList>();

function TabIcon({emoji, label, cor, pendentes}: {emoji: string; label: string; cor: string; pendentes?: number}) {
  return (
    <View style={{alignItems: 'center'}}>
      <View>
        <Text style={{fontSize: 20, color: cor}}>{emoji}</Text>
        {pendentes ? (
          <View style={{
            position: 'absolute', top: -4, right: -8,
            backgroundColor: CORES.vermelho, borderRadius: 8,
            minWidth: 16, height: 16, alignItems: 'center', justifyContent: 'center',
            paddingHorizontal: 3,
          }}>
            <Text style={{color: '#fff', fontSize: 9, fontWeight: '800'}}>{pendentes}</Text>
          </View>
        ) : null}
      </View>
    </View>
  );
}

function AppTabs() {
  const {pendentes} = useOfflineQueue();

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
          tabBarIcon: ({color}) => (
            <TabIcon emoji="📋" label="Ordens" cor={color} pendentes={pendentes.length} />
          ),
        }}
      />
      <AppTab.Screen
        name="Produtividade"
        component={ProdutividadeScreen}
        options={{
          headerShown: true,
          title: 'Minha Produtividade',
          headerStyle: {backgroundColor: CORES.branco},
          headerTitleStyle: {fontWeight: '800', color: CORES.cinza900, fontSize: 16},
          tabBarLabel: 'Produtividade',
          tabBarIcon: ({color}) => <TabIcon emoji="📊" label="Produtividade" cor={color} />,
        }}
      />
      <AppTab.Screen
        name="Profile"
        component={ProfileScreen}
        options={{
          tabBarLabel: 'Perfil',
          tabBarIcon: ({color}) => <TabIcon emoji="👤" label="Perfil" cor={color} />,
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
            <RootStack.Screen
              name="CreateOs"
              component={CreateOsScreen}
              options={{
                headerShown: true,
                title: 'Nova OS',
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
