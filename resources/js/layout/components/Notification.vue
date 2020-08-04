<template>
  <el-dropdown class="avatar-container right-menu-item hover-effect" trigger="click">
    <div class="">
      <i class="el-icon-bell" />
    </div>
    <el-dropdown-menu slot="dropdown">
      <el-dropdown-item divided>
        <span style="display:block;">{{ $t('navbar.logOut') }}</span>
      </el-dropdown-item>
    </el-dropdown-menu>
  </el-dropdown>
</template>
