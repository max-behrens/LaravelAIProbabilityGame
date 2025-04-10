import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { UserComponent, UserService } from './resources/js/angular/user.component';

@NgModule({
  declarations: [],
  imports: [
    BrowserModule,
    UserComponent
  ],
  providers: [UserService],
  bootstrap: [UserComponent]
})
export class AppModule { }