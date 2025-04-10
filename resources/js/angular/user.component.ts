import { Component, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { CommonModule } from '@angular/common';

@Injectable({ providedIn: 'root' })
export class UserService {
  constructor() { }
  
  public getCurrentUsername(): Observable<string> {
    return new Observable((observer) => {
      observer.next("Admin");
    });
  }
}

@Component({
  selector: 'app-user',
  standalone: true,
  imports: [CommonModule],
  template: '<p>Username: {{ username$ | async }}</p>'
})
export class UserComponent {
  username$: Observable<string>;
  
  constructor(private userService: UserService) {
    this.username$ = this.userService.getCurrentUsername();
  }
}